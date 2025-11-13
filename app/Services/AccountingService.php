<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

/**
 * Accounting Service
 * Handles double-entry bookkeeping with idempotent posting
 */
class AccountingService
{
    private PDO $db;
    private ?bool $expenseCategoriesHaveAccountCode = null;
    private array $journalEntriesColumnPresence = [];

    // Standard account codes
    private const ACCOUNT_CASH = '1000';
    private const ACCOUNT_BANK = '1100';
    private const ACCOUNT_AR = '1200';
    private const ACCOUNT_INVENTORY = '1300';
    private const ACCOUNT_AP = '2000';
    private const ACCOUNT_REVENUE = '4000';
    private const ACCOUNT_COGS = '5000';
    private const ACCOUNT_DEFAULT_EXPENSE = '6000';
    private const ACCOUNT_TAX_PAYABLE = '2100';
    private const ACCOUNT_TAX_RECOVERABLE = '1410';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Post sale to accounting (idempotent)
     */
    public function postSale(int $saleId, array $totals): void
    {
        $referenceNo = "SALE-{$saleId}";

        if ($this->isPosted('sale', $saleId, $referenceNo)) {
            return;
        }

        $entryDate = date('Y-m-d');
        if ($this->isPeriodLocked($entryDate)) {
            throw new Exception('Accounting period is locked for ' . $entryDate);
        }

        try {
            $this->db->beginTransaction();

            $periodId = $this->resolvePeriod($entryDate);

            $journalId = $this->createJournalEntry([
                'entry_number' => $this->generateEntryNumber(),
                'source' => 'sale',
                'source_id' => $saleId,
                'reference_no' => $referenceNo,
                'entry_date' => $entryDate,
                'description' => "Sale #{$saleId}",
                'total_debit' => $totals['total'],
                'total_credit' => $totals['total'],
                'period_id' => $periodId,
            ]);

            $lines = [];

            $cashAccount = $totals['payment_method'] === 'credit' ? self::ACCOUNT_AR : self::ACCOUNT_CASH;
            $lines[] = ['account' => $cashAccount, 'debit' => $totals['total'], 'credit' => 0, 'description' => 'Tender received'];

            $revenueAmount = max(0, $totals['subtotal'] - $totals['discount']);
            if ($revenueAmount > 0) {
                $lines[] = ['account' => self::ACCOUNT_REVENUE, 'debit' => 0, 'credit' => $revenueAmount, 'description' => 'Sales revenue'];
            }

            if ($totals['discount'] > 0) {
                $lines[] = ['account' => '4510', 'debit' => $totals['discount'], 'credit' => 0, 'description' => 'Sales discount'];
            }

            if ($totals['tax'] > 0) {
                $lines[] = ['account' => self::ACCOUNT_TAX_PAYABLE, 'debit' => 0, 'credit' => $totals['tax'], 'description' => 'Sales tax payable'];
            }

            $this->storeJournalLines($journalId, $lines);

            $this->postCOGS($saleId, $entryDate, $periodId);

            $this->markAsPosted($journalId);

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();

            if ($e->getCode() == 23000) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Post expense voucher into the ledger
     */
    public function postExpense(int $expenseId, array $payload): void
    {
        $referenceNo = $payload['reference'] ?? "EXP-{$expenseId}";

        if ($this->isPosted('expense', $expenseId, $referenceNo)) {
            return;
        }

        $entryDate = $payload['expense_date'] ?? date('Y-m-d');
        if ($this->isPeriodLocked($entryDate)) {
            throw new Exception('Accounting period is locked for ' . $entryDate);
        }

        $grossAmount = (float) ($payload['amount'] ?? 0);
        $taxAmount = max(0, (float) ($payload['tax_amount'] ?? 0));

        if ($grossAmount <= 0) {
            throw new Exception('Expense amount must be greater than zero.');
        }

        if ($taxAmount > $grossAmount) {
            throw new Exception('Expense tax amount cannot exceed total amount.');
        }

        $netAmount = $grossAmount - $taxAmount;

        $paymentMethod = strtolower($payload['payment_method'] ?? 'cash');
        $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : null;

        $manageTransaction = !$this->db->inTransaction();

        try {
            if ($manageTransaction) {
                $this->db->beginTransaction();
            }

            $periodId = $this->resolvePeriod($entryDate);
            $journalId = $this->createJournalEntry([
                'entry_number' => $this->generateEntryNumber(),
                'source' => 'expense',
                'source_id' => $expenseId,
                'reference_no' => $referenceNo,
                'entry_date' => $entryDate,
                'description' => $payload['description'] ?? "Expense #{$expenseId}",
                'total_debit' => $grossAmount,
                'total_credit' => $grossAmount,
                'period_id' => $periodId,
            ]);

            $lines = [];

            if ($netAmount > 0) {
                $expenseAccount = $this->resolveExpenseAccount($categoryId);
                $lines[] = [
                    'account' => $expenseAccount,
                    'debit' => $netAmount,
                    'credit' => 0,
                    'description' => 'Expense'
                ];
            }

            if ($taxAmount > 0) {
                $lines[] = [
                    'account' => self::ACCOUNT_TAX_RECOVERABLE,
                    'debit' => $taxAmount,
                    'credit' => 0,
                    'description' => 'Input tax'
                ];
            }

            $creditAccount = $this->resolveDisbursementAccount($paymentMethod);
            $lines[] = [
                'account' => $creditAccount,
                'debit' => 0,
                'credit' => $grossAmount,
                'description' => 'Payment'
            ];

            $this->storeJournalLines($journalId, $lines);
            $this->markAsPosted($journalId);

            if ($manageTransaction) {
                $this->db->commit();
            }
        } catch (PDOException $e) {
            if ($manageTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($e->getCode() == 23000) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Post manual journal entry (e.g. adjustments, accruals)
     */
    public function postManualEntry(array $payload): int
    {
        $entryDate = $payload['entry_date'] ?? date('Y-m-d');
        $description = $payload['description'] ?? 'Manual journal entry';
        $reference = $payload['reference'] ?? ('MANUAL-' . uniqid());
        $lines = $payload['lines'] ?? [];

        if (empty($lines) || !is_array($lines)) {
            throw new Exception('Manual journal entry requires at least one line.');
        }

        if ($this->isPeriodLocked($entryDate)) {
            throw new Exception('Accounting period locked for ' . $entryDate);
        }

        $totalDebits = 0.0;
        $totalCredits = 0.0;
        foreach ($lines as $line) {
            if (!isset($line['account_id']) && empty($line['account'])) {
                throw new Exception('Each journal line must reference an account.');
            }
            $totalDebits += (float) ($line['debit'] ?? 0);
            $totalCredits += (float) ($line['credit'] ?? 0);
        }

        if (abs($totalDebits - $totalCredits) > 0.01) {
            throw new Exception('Manual journal entry must balance.');
        }

        $manageTransaction = !$this->db->inTransaction();

        try {
            if ($manageTransaction) {
                $this->db->beginTransaction();
            }

            $journalId = $this->createJournalEntry([
                'entry_number' => $this->generateEntryNumber(),
                'source' => 'manual',
                'source_id' => null,
                'reference_no' => $reference,
                'entry_date' => $entryDate,
                'description' => $description,
                'total_debit' => $totalDebits,
                'total_credit' => $totalCredits,
                'period_id' => $this->resolvePeriod($entryDate),
                'status' => 'draft'
            ]);

            $this->storeJournalLines($journalId, $lines);
            $this->markAsPosted($journalId);

            if ($manageTransaction) {
                $this->db->commit();
            }

            return $journalId;
        } catch (Exception $e) {
            if ($manageTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Sum ledger movement by account types (credit minus debit by default)
     */
    public function sumByAccountTypes(array $types, string $startDate, string $endDate, bool $creditMinusDebit = true): float
    {
        if (empty($types)) {
            return 0.0;
        }

        $expression = $creditMinusDebit ? 'SUM(jel.credit_amount - jel.debit_amount)' : 'SUM(jel.debit_amount - jel.credit_amount)';
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $statusFilter = $this->getPostedFilter();

        $sql = "SELECT COALESCE({$expression}, 0) AS total
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                JOIN accounts a ON a.id = jel.account_id
                WHERE {$statusFilter}
                  AND je.entry_date BETWEEN ? AND ?
                  AND a.type IN ({$placeholders})";

        $params = array_merge([$startDate, $endDate], $types);
        return $this->fetchAggregate($sql, $params);
    }

    public function sumAsOfByAccountIds(array $accountIds, string $asOfDate, bool $creditMinusDebit = false): float
    {
        if (empty($accountIds)) {
            return 0.0;
        }

        $expression = $creditMinusDebit ? 'SUM(jel.credit_amount - jel.debit_amount)' : 'SUM(jel.debit_amount - jel.credit_amount)';
        $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
        $statusFilter = $this->getPostedFilter();

        $sql = "SELECT COALESCE({$expression}, 0) AS total
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                WHERE {$statusFilter}
                  AND je.entry_date <= ?
                  AND jel.account_id IN ({$placeholders})";

        $params = array_merge([$asOfDate], $accountIds);
        return $this->fetchAggregate($sql, $params);
    }

    private function resolveExpenseAccount(?int $categoryId): string
    {
        if (!$categoryId) {
            return self::ACCOUNT_DEFAULT_EXPENSE;
        }

        if ($this->expenseCategoriesHaveAccountCode === null) {
            $stmt = $this->db->query("SELECT COUNT(*) AS column_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expense_categories' AND COLUMN_NAME = 'account_code'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->expenseCategoriesHaveAccountCode = !empty($result['column_exists']);
        }

        if (!$this->expenseCategoriesHaveAccountCode) {
            return self::ACCOUNT_DEFAULT_EXPENSE;
        }

        $stmt = $this->db->prepare("SELECT account_code FROM expense_categories WHERE id = ? LIMIT 1");
        $stmt->execute([$categoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['account_code'])) {
            return self::ACCOUNT_DEFAULT_EXPENSE;
        }

        return $row['account_code'];
    }

    private function resolveDisbursementAccount(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'cash' => self::ACCOUNT_CASH,
            'bank_transfer', 'card', 'mobile_money', 'cheque' => self::ACCOUNT_BANK,
            'credit', 'accounts_payable' => self::ACCOUNT_AP,
            default => self::ACCOUNT_BANK,
        };
    }

    private function fetchAggregate(string $sql, array $params): float
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($row['total']) ? (float) $row['total'] : 0.0;
    }

    private function getPostedFilter(): string
    {
        return $this->hasJournalEntriesColumn('status') ? "je.status = 'posted'" : '1=1';
    }

    private function hasJournalEntriesColumn(string $column): bool
    {
        if (array_key_exists($column, $this->journalEntriesColumnPresence)) {
            return $this->journalEntriesColumnPresence[$column];
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS column_exists
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'journal_entries'
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$column]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->journalEntriesColumnPresence[$column] = !empty($result['column_exists']);

        return $this->journalEntriesColumnPresence[$column];
    }

    /**
     * Sum ledger movement by account codes (debit minus credit by default)
     */
    public function sumByAccountCodes(array $codes, string $startDate, string $endDate, bool $creditMinusDebit = false): float
    {
        if (empty($codes)) {
            return 0.0;
        }

        $expression = $creditMinusDebit ? 'SUM(jel.credit_amount - jel.debit_amount)' : 'SUM(jel.debit_amount - jel.credit_amount)';
        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        $statusFilter = $this->getPostedFilter();

        $sql = "SELECT COALESCE({$expression}, 0) AS total
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                JOIN accounts a ON a.id = jel.account_id
                WHERE {$statusFilter}
                  AND je.entry_date BETWEEN ? AND ?
                  AND a.code IN ({$placeholders})";

        $params = array_merge([$startDate, $endDate], $codes);
        return $this->fetchAggregate($sql, $params);
    }

    public function sumByAccountClassifications(array $classifications, string $startDate, string $endDate, bool $creditMinusDebit = false): float
    {
        if (empty($classifications)) {
            return 0.0;
        }

        $expression = $creditMinusDebit ? 'SUM(jel.credit_amount - jel.debit_amount)' : 'SUM(jel.debit_amount - jel.credit_amount)';
        $placeholders = implode(',', array_fill(0, count($classifications), '?'));

        $statusFilter = $this->getPostedFilter();

        $sql = "SELECT COALESCE({$expression}, 0) AS total
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                JOIN accounts a ON a.id = jel.account_id
                WHERE {$statusFilter}
                  AND je.entry_date BETWEEN ? AND ?
                  AND a.classification IN ({$placeholders})";

        $params = array_merge([$startDate, $endDate], $classifications);
        return $this->fetchAggregate($sql, $params);
    }

    /**
     * Sum balances up to an as-of date
     */
    public function sumAsOfByAccountTypes(array $types, string $asOfDate, bool $creditMinusDebit = false): float
    {
        if (empty($types)) {
            return 0.0;
        }

        $expression = $creditMinusDebit ? 'SUM(jel.credit_amount - jel.debit_amount)' : 'SUM(jel.debit_amount - jel.credit_amount)';
        $placeholders = implode(',', array_fill(0, count($types), '?'));

        $statusFilter = $this->getPostedFilter();

        $sql = "SELECT COALESCE({$expression}, 0) AS total
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                JOIN accounts a ON a.id = jel.account_id
                WHERE {$statusFilter}
                  AND je.entry_date <= ?
                  AND a.type IN ({$placeholders})";

        $params = array_merge([$asOfDate], $types);
        return $this->fetchAggregate($sql, $params);
    }

    public function sumAsOfByAccountCodes(array $codes, string $asOfDate, bool $creditMinusDebit = false): float
    {
        if (empty($codes)) {
            return 0.0;
        }

        $expression = $creditMinusDebit ? 'SUM(jel.credit_amount - jel.debit_amount)' : 'SUM(jel.debit_amount - jel.credit_amount)';
        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        $statusFilter = $this->getPostedFilter();

        $sql = "SELECT COALESCE({$expression}, 0) AS total
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                JOIN accounts a ON a.id = jel.account_id
                WHERE {$statusFilter}
                  AND je.entry_date <= ?
                  AND a.code IN ({$placeholders})";

        $params = array_merge([$asOfDate], $codes);
        return $this->fetchAggregate($sql, $params);
    }

    /**
     * Fetch tax totals for VAT reporting
     */
    public function getTaxTotals(string $startDate, string $endDate): array
    {
        $outputTax = $this->sumByAccountCodes([self::ACCOUNT_TAX_PAYABLE], $startDate, $endDate, true);
        $inputTax = $this->sumByAccountCodes([self::ACCOUNT_TAX_RECOVERABLE], $startDate, $endDate, false);

        return [
            'output_tax' => $outputTax,
            'input_tax' => $inputTax,
            'net_tax' => $outputTax - $inputTax,
        ];
    }

    /**
     * Post COGS for sale
     */
    private function postCOGS(int $saleId, string $entryDate, ?int $periodId): void
    {
        // Get sale items with cost
        $sql = "SELECT si.*, p.cost_price 
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$saleId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalCost = 0;
        foreach ($items as $item) {
            $totalCost += $item['quantity'] * ($item['cost_price'] ?? 0);
        }

        if ($totalCost > 0) {
            $referenceNo = "COGS-{$saleId}";

            if ($this->isPosted('cogs', $saleId, $referenceNo)) {
                return;
            }

            if ($this->isPeriodLocked($entryDate)) {
                throw new Exception('Accounting period is locked for ' . $entryDate);
            }

            $journalId = $this->createJournalEntry([
                'entry_number' => $this->generateEntryNumber(),
                'source' => 'cogs',
                'source_id' => $saleId,
                'reference_no' => $referenceNo,
                'entry_date' => $entryDate,
                'description' => "COGS for Sale #{$saleId}",
                'total_debit' => $totalCost,
                'total_credit' => $totalCost,
                'period_id' => $periodId,
            ]);

            $this->storeJournalLines($journalId, [
                ['account' => self::ACCOUNT_COGS, 'debit' => $totalCost, 'credit' => 0, 'description' => 'Cost of goods sold'],
                ['account' => self::ACCOUNT_INVENTORY, 'debit' => 0, 'credit' => $totalCost, 'description' => 'Inventory relief'],
            ]);

            $this->markAsPosted($journalId);
        }
    }

    /**
     * Post refund/void (reversal entry)
     */
    public function postRefund(int $saleId, int $originalSaleId): void
    {
        $referenceNo = "REFUND-{$saleId}";
        
        if ($this->isPosted('refund', $saleId, $referenceNo)) {
            return;
        }

        try {
            $this->db->beginTransaction();

            // Get original sale totals
            $sql = "SELECT * FROM sales WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$originalSaleId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                throw new \Exception("Original sale not found");
            }

            $entryNumber = $this->generateEntryNumber();
            
            // Create reversal entry
            $entryDate = date('Y-m-d');
            if ($this->isPeriodLocked($entryDate)) {
                throw new Exception('Accounting period is locked for ' . $entryDate);
            }

            $periodId = $this->resolvePeriod($entryDate);

            $journalId = $this->createJournalEntry([
                'entry_number' => $entryNumber,
                'source' => 'refund',
                'source_id' => $saleId,
                'reference_no' => $referenceNo,
                'entry_date' => $entryDate,
                'description' => "Refund for Sale #{$originalSaleId}",
                'total_debit' => $sale['total_amount'],
                'total_credit' => $sale['total_amount'],
                'period_id' => $periodId,
            ]);

            $lines = [
                ['account' => self::ACCOUNT_CASH, 'debit' => 0, 'credit' => $sale['total_amount'], 'description' => 'Refund cash outflow'],
            ];

            $revenueAmount = max(0, $sale['subtotal'] - $sale['discount_amount']);
            if ($revenueAmount > 0) {
                $lines[] = ['account' => self::ACCOUNT_REVENUE, 'debit' => $revenueAmount, 'credit' => 0, 'description' => 'Reverse revenue'];
            }

            if ($sale['discount_amount'] > 0) {
                $lines[] = ['account' => '4510', 'debit' => 0, 'credit' => $sale['discount_amount'], 'description' => 'Reverse discount'];
            }

            if ($sale['tax_amount'] > 0) {
                $lines[] = ['account' => self::ACCOUNT_TAX_PAYABLE, 'debit' => $sale['tax_amount'], 'credit' => 0, 'description' => 'Reverse tax liability'];
            }

            $this->storeJournalLines($journalId, $lines);

            $this->markAsPosted($journalId);
            $this->db->commit();

        } catch (PDOException $e) {
            $this->db->rollBack();
            
            if ($e->getCode() == 23000) {
                return;
            }
            
            throw $e;
        }
    }

    /**
     * Close accounting period
     */
    public function closePeriod(string $startDate, string $endDate, int $userId): int
    {
        $sql = "INSERT INTO accounting_periods 
                (period_name, start_date, end_date, status, closed_by, closed_at) 
                VALUES (?, ?, ?, 'closed', ?, NOW())";
        
        $periodName = date('M Y', strtotime($startDate));
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$periodName, $startDate, $endDate, $userId]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Lock accounting period (prevents edits)
     */
    public function lockPeriod(int $periodId, int $userId): void
    {
        $sql = "UPDATE accounting_periods 
                SET status = 'locked', closed_by = ?, closed_at = NOW() 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $periodId]);
    }

    /**
     * Check if period is locked
     */
    public function isPeriodLocked(string $date): bool
    {
        $sql = "SELECT COUNT(*) as count FROM accounting_periods 
                WHERE ? BETWEEN start_date AND end_date 
                AND status = 'locked'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }

    /**
     * Check if transaction already posted (idempotent check)
     */
    private function isPosted(string $source, int $sourceId, string $referenceNo): bool
    {
        $sql = "SELECT COUNT(*) as count FROM journal_entries 
                WHERE source = ? AND source_id = ? AND reference_no = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$source, $sourceId, $referenceNo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }

    /**
     * Create journal entry
     */
    private function createJournalEntry(array $data): int
    {
        $sql = "INSERT INTO journal_entries 
                (entry_number, source, source_id, reference_no, entry_date, 
                 description, total_debit, total_credit, status, period_id, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['entry_number'],
            $data['source'],
            $data['source_id'],
            $data['reference_no'],
            $data['entry_date'],
            $data['description'],
            $data['total_debit'],
            $data['total_credit'],
            $data['status'] ?? 'draft',
            $data['period_id'] ?? null,
            $_SESSION['user_id'] ?? 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Add journal entry line
     */
    private function storeJournalLines(int $journalId, array $lines): void
    {
        foreach ($lines as $line) {
            $accountId = $line['account_id'] ?? null;

            if ($accountId === null) {
                if (empty($line['account'])) {
                    throw new Exception('Journal line requires either account code or account_id.');
                }
                $account = $this->findAccount($line['account']);
                $accountId = (int) $account['id'];
            }

            $sql = "INSERT INTO journal_entry_lines 
                    (journal_entry_id, account_id, debit_amount, credit_amount, description, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $journalId,
                $accountId,
                $line['debit'],
                $line['credit'],
                $line['description'] ?? null,
            ]);
        }
    }

    private function findAccount(string $code): array
    {
        $sql = "SELECT id FROM accounts WHERE code = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$code]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            throw new Exception("Account {$code} not found");
        }

        return $account;
    }

    /**
     * Mark journal entry as posted
     */
    private function markAsPosted(int $journalId): void
    {
        $setParts = [];
        $params = [];

        if ($this->hasJournalEntriesColumn('status')) {
            $setParts[] = "status = 'posted'";
        }

        if ($this->hasJournalEntriesColumn('posted_by')) {
            $setParts[] = 'posted_by = ?';
            $params[] = $_SESSION['user_id'] ?? 1;
        }

        if ($this->hasJournalEntriesColumn('posted_at')) {
            $setParts[] = 'posted_at = NOW()';
        }

        if (empty($setParts)) {
            return;
        }

        $sql = 'UPDATE journal_entries SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $params[] = $journalId;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Generate entry number
     */
    private function generateEntryNumber(): string
    {
        $prefix = 'JE';
        $date = date('Ymd');
        
        $sql = "SELECT entry_number FROM journal_entries 
                WHERE entry_number LIKE ? 
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["{$prefix}-{$date}-%"]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($last) {
            $parts = explode('-', $last['entry_number']);
            $sequence = (int) end($parts) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    /**
     * Get trial balance
     */
    public function getTrialBalance(): array
    {
        $sql = "SELECT a.code, a.name, a.type,
                SUM(CASE WHEN je.status = 'posted' THEN jel.debit_amount ELSE 0 END) AS total_debit,
                SUM(CASE WHEN je.status = 'posted' THEN jel.credit_amount ELSE 0 END) AS total_credit,
                SUM(CASE WHEN je.status = 'posted' THEN jel.debit_amount - jel.credit_amount ELSE 0 END) AS balance
                FROM accounts a
                LEFT JOIN journal_entry_lines jel ON a.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                GROUP BY a.id, a.code, a.name, a.type
                ORDER BY a.code";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function resolvePeriod(string $date): ?int
    {
        $sql = "SELECT id FROM accounting_periods WHERE ? BETWEEN start_date AND end_date LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        return $period ? (int) $period['id'] : null;
    }
}
