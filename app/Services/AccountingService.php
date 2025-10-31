<?php

namespace App\Services;

use PDO;
use PDOException;

/**
 * Accounting Service
 * Handles double-entry bookkeeping with idempotent posting
 */
class AccountingService
{
    private PDO $db;

    // Standard account codes
    private const ACCOUNT_CASH = '1000';
    private const ACCOUNT_AR = '1100';
    private const ACCOUNT_INVENTORY = '1200';
    private const ACCOUNT_AP = '2000';
    private const ACCOUNT_REVENUE = '4000';
    private const ACCOUNT_COGS = '5000';
    private const ACCOUNT_TAX_PAYABLE = '2100';

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
        
        // Check if already posted (idempotent)
        if ($this->isPosted('sale', $saleId, $referenceNo)) {
            return;
        }

        try {
            $this->db->beginTransaction();

            $entryNumber = $this->generateEntryNumber();
            
            // Create journal entry
            $journalId = $this->createJournalEntry([
                'entry_number' => $entryNumber,
                'source' => 'sale',
                'source_id' => $saleId,
                'reference_no' => $referenceNo,
                'entry_date' => date('Y-m-d'),
                'description' => "Sale #{$saleId}",
                'total_debit' => $totals['total'],
                'total_credit' => $totals['total']
            ]);

            // Debit: Cash/AR (Asset increases)
            $this->addJournalLine($journalId, self::ACCOUNT_CASH, $totals['total'], 0);

            // Credit: Revenue (Revenue increases)
            $revenueAmount = $totals['subtotal'] - $totals['discount'];
            $this->addJournalLine($journalId, self::ACCOUNT_REVENUE, 0, $revenueAmount);

            // Credit: Tax Payable
            if ($totals['tax'] > 0) {
                $this->addJournalLine($journalId, self::ACCOUNT_TAX_PAYABLE, 0, $totals['tax']);
            }

            // Post COGS (if we have cost data)
            $this->postCOGS($saleId);

            // Mark as posted
            $this->markAsPosted($journalId);

            $this->db->commit();

        } catch (PDOException $e) {
            $this->db->rollBack();
            
            // If duplicate, it's already posted (idempotent behavior)
            if ($e->getCode() == 23000) {
                return;
            }
            
            throw $e;
        }
    }

    /**
     * Post COGS for sale
     */
    private function postCOGS(int $saleId): void
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
            
            // Check if already posted
            if ($this->isPosted('cogs', $saleId, $referenceNo)) {
                return;
            }

            $entryNumber = $this->generateEntryNumber();
            
            $journalId = $this->createJournalEntry([
                'entry_number' => $entryNumber,
                'source' => 'cogs',
                'source_id' => $saleId,
                'reference_no' => $referenceNo,
                'entry_date' => date('Y-m-d'),
                'description' => "COGS for Sale #{$saleId}",
                'total_debit' => $totalCost,
                'total_credit' => $totalCost
            ]);

            // Debit: COGS (Expense increases)
            $this->addJournalLine($journalId, self::ACCOUNT_COGS, $totalCost, 0);

            // Credit: Inventory (Asset decreases)
            $this->addJournalLine($journalId, self::ACCOUNT_INVENTORY, 0, $totalCost);

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
            $journalId = $this->createJournalEntry([
                'entry_number' => $entryNumber,
                'source' => 'refund',
                'source_id' => $saleId,
                'reference_no' => $referenceNo,
                'entry_date' => date('Y-m-d'),
                'description' => "Refund for Sale #{$originalSaleId}",
                'total_debit' => $sale['total_amount'],
                'total_credit' => $sale['total_amount']
            ]);

            // Reverse the original entries
            // Credit: Cash (Asset decreases)
            $this->addJournalLine($journalId, self::ACCOUNT_CASH, 0, $sale['total_amount']);

            // Debit: Revenue (Revenue decreases)
            $revenueAmount = $sale['subtotal'] - $sale['discount_amount'];
            $this->addJournalLine($journalId, self::ACCOUNT_REVENUE, $revenueAmount, 0);

            // Debit: Tax Payable
            if ($sale['tax_amount'] > 0) {
                $this->addJournalLine($journalId, self::ACCOUNT_TAX_PAYABLE, $sale['tax_amount'], 0);
            }

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
                 description, total_debit, total_credit, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
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
            $_SESSION['user_id'] ?? 1
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Add journal entry line
     */
    private function addJournalLine(int $journalId, string $accountCode, float $debit, float $credit): void
    {
        // Get account ID
        $sql = "SELECT id FROM accounts WHERE code = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$accountCode]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            throw new \Exception("Account {$accountCode} not found");
        }

        $sql = "INSERT INTO journal_entry_lines 
                (journal_entry_id, account_id, debit_amount, credit_amount) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$journalId, $account['id'], $debit, $credit]);
    }

    /**
     * Mark journal entry as posted
     */
    private function markAsPosted(int $journalId): void
    {
        $sql = "UPDATE journal_entries 
                SET is_posted = 1, posted_by = ?, posted_at = NOW() 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$_SESSION['user_id'] ?? 1, $journalId]);
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
        $sql = "SELECT a.code, a.name, a.account_type,
                SUM(jel.debit_amount) as total_debit,
                SUM(jel.credit_amount) as total_credit,
                SUM(jel.debit_amount - jel.credit_amount) as balance
                FROM accounts a
                LEFT JOIN journal_entry_lines jel ON a.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE je.is_posted = 1 OR je.id IS NULL
                GROUP BY a.id, a.code, a.name, a.account_type
                ORDER BY a.code";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
