<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

/**
 * Ledger data provider aggregating IFRS-compliant journal metrics
 * for dashboards and financial reports.
 */
class LedgerDataService
{
    private PDO $db;
    private AccountingService $accountingService;
    private array $journalEntriesColumnCache = [];

    public function __construct(PDO $db, AccountingService $accountingService)
    {
        $this->db = $db;
        $this->accountingService = $accountingService;
    }

    /**
     * Compile headline financial metrics for dashboards/reports.
     */
    public function getFinancialSummary(string $startDate, string $endDate): array
    {
        $revenue = $this->accountingService->sumByAccountTypes(['REVENUE', 'OTHER_INCOME'], $startDate, $endDate, true);
        $cogs = $this->accountingService->sumByAccountClassifications(['COST_OF_SALES'], $startDate, $endDate, false);
        $operatingExpenses = $this->accountingService->sumByAccountClassifications(['OPERATING_EXPENSE'], $startDate, $endDate, false);
        $nonOperatingExpenses = $this->accountingService->sumByAccountClassifications(['NON_OPERATING_EXPENSE', 'OTHER_EXPENSE'], $startDate, $endDate, false);

        $grossProfit = $revenue - $cogs;
        $totalExpenses = $operatingExpenses + $nonOperatingExpenses;
        $netProfit = $grossProfit - $totalExpenses;
        $margin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;

        return [
            'revenue_total' => $revenue,
            'cogs_total' => $cogs,
            'operating_expense_total' => $operatingExpenses,
            'non_operating_expense_total' => $nonOperatingExpenses,
            'total_expense' => $totalExpenses,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'profit_margin' => $margin,
        ];
    }

    /**
     * Detailed profit and loss statement metrics for IFRS presentation.
     */
    public function getProfitAndLoss(string $startDate, string $endDate): array
    {
        $revenue = $this->accountingService->sumByAccountTypes(['REVENUE', 'OTHER_INCOME'], $startDate, $endDate, true);
        $contraRevenue = $this->accountingService->sumByAccountTypes(['CONTRA_REVENUE'], $startDate, $endDate, false);
        $netRevenue = $revenue - $contraRevenue;

        $cogs = $this->accountingService->sumByAccountClassifications(['COST_OF_SALES'], $startDate, $endDate, false);
        $operatingExpenses = $this->accountingService->sumByAccountClassifications(['OPERATING_EXPENSE'], $startDate, $endDate, false);
        $nonOperatingExpenses = $this->accountingService->sumByAccountClassifications(['NON_OPERATING_EXPENSE', 'OTHER_EXPENSE'], $startDate, $endDate, false);

        $grossProfit = $netRevenue - $cogs;
        $totalExpenses = $operatingExpenses + $nonOperatingExpenses;
        $netProfit = $grossProfit - $totalExpenses;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'revenue' => $revenue,
            'contra_revenue' => $contraRevenue,
            'net_revenue' => $netRevenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'non_operating_expenses' => $nonOperatingExpenses,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
        ];
    }

    /**
     * Retrieve aggregated expense balances by account classification hierarchy.
     */
    public function getExpenseBreakdown(string $startDate, string $endDate, array $classifications = ['OPERATING_EXPENSE', 'NON_OPERATING_EXPENSE', 'OTHER_EXPENSE', 'COST_OF_SALES']): array
    {
        if (empty($classifications)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($classifications), '?'));

        $statusFilter = $this->postedFilter();

        $sql = "SELECT a.id,
                       a.code AS account_code,
                       a.name AS account_name,
                       a.classification,
                       a.reporting_order,
                       COALESCE(SUM(jel.debit_amount - jel.credit_amount), 0) AS total
                FROM accounts a
                JOIN journal_entry_lines jel ON jel.account_id = a.id
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                WHERE {$statusFilter}
                  AND je.entry_date BETWEEN ? AND ?
                  AND a.classification IN ({$placeholders})
                GROUP BY a.id, a.code, a.name, a.classification, a.reporting_order
                ORDER BY a.reporting_order ASC, a.code ASC";

        $params = array_merge([$startDate, $endDate], $classifications);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetch recent expense or cost journal movements for drill-down tables.
     */
    public function getRecentExpenseEntries(string $startDate, string $endDate, int $limit = 50): array
    {
        $limit = max(1, min($limit, 500));

        $statusFilter = $this->postedFilter();
        $entryNumberColumn = $this->hasJournalEntriesColumn('entry_number') ? 'je.entry_number' : 'NULL';
        $referenceColumn = $this->hasJournalEntriesColumn('reference_no')
            ? 'je.reference_no'
            : ($this->hasJournalEntriesColumn('reference') ? 'je.reference' : 'NULL');

        $sql = "SELECT je.id AS journal_entry_id,
                       {$entryNumberColumn} AS entry_number,
                       je.entry_date,
                       {$referenceColumn} AS reference_no,
                       je.description,
                       jel.debit_amount,
                       jel.credit_amount,
                       a.code AS account_code,
                       a.name AS account_name
                FROM journal_entries je
                JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id
                JOIN accounts a ON a.id = jel.account_id
                WHERE {$statusFilter}
                  AND je.entry_date BETWEEN ? AND ?
                  AND a.classification IN ('OPERATING_EXPENSE','NON_OPERATING_EXPENSE','OTHER_EXPENSE','COST_OF_SALES')
                ORDER BY je.entry_date DESC, je.id DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Provide chart-friendly labels/datasets for expense distribution.
     */
    public function getExpenseChartData(string $startDate, string $endDate): array
    {
        $breakdown = $this->getExpenseBreakdown($startDate, $endDate);

        $labels = [];
        $values = [];
        foreach ($breakdown as $row) {
            $labels[] = $row['account_code'] . ' - ' . $row['account_name'];
            $values[] = (float) $row['total'];
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'raw' => $breakdown,
        ];
    }

    /**
     * Compute VAT totals for reporting periods.
     */
    public function getVatSummary(string $startDate, string $endDate): array
    {
        return $this->accountingService->getTaxTotals($startDate, $endDate);
    }

    /**
     * Trial balance-style aggregation across all accounts as of end date.
     */
    public function getTrialBalance(string $asOfDate): array
    {
        $hasStatus = $this->hasJournalEntriesColumn('status');
        $debitExpr = $hasStatus
            ? "SUM(CASE WHEN je.status = 'posted' THEN jel.debit_amount ELSE 0 END)"
            : 'SUM(jel.debit_amount)';
        $creditExpr = $hasStatus
            ? "SUM(CASE WHEN je.status = 'posted' THEN jel.credit_amount ELSE 0 END)"
            : 'SUM(jel.credit_amount)';
        $balanceExpr = $hasStatus
            ? "SUM(CASE WHEN je.status = 'posted' THEN jel.debit_amount - jel.credit_amount ELSE 0 END)"
            : 'SUM(jel.debit_amount - jel.credit_amount)';

        $sql = "SELECT a.code,
                       a.name,
                       a.type,
                       a.classification,
                       {$debitExpr} AS total_debit,
                       {$creditExpr} AS total_credit,
                       {$balanceExpr} AS balance
                FROM accounts a
                LEFT JOIN journal_entry_lines jel ON a.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE je.entry_date <= ? OR je.entry_date IS NULL
                GROUP BY a.id, a.code, a.name, a.type, a.classification
                ORDER BY a.reporting_order ASC, a.code ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$asOfDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Build balance sheet totals (assets, liabilities, equity) as of a date.
     */
    public function getBalanceSheet(string $asOfDate): array
    {
        $trialBalance = $this->getTrialBalance($asOfDate);
        $profitAndLossToDate = $this->getProfitAndLoss('1970-01-01', $asOfDate);
        $netIncome = $profitAndLossToDate['net_profit'];

        $assetClasses = ['CURRENT_ASSET', 'NON_CURRENT_ASSET', 'CONTRA_ASSET'];
        $liabilityClasses = ['CURRENT_LIABILITY', 'NON_CURRENT_LIABILITY', 'CONTRA_LIABILITY'];
        $equityClasses = ['EQUITY', 'CONTRA_EQUITY'];

        $assetsTotal = 0.0;
        $liabilitiesTotal = 0.0;
        $equityAccountsTotal = 0.0;

        foreach ($trialBalance as $row) {
            $classification = $row['classification'] ?? '';
            $balance = (float) ($row['balance'] ?? 0);

            if (in_array($classification, $assetClasses, true)) {
                $assetsTotal += $balance;
            } elseif (in_array($classification, $liabilityClasses, true)) {
                $liabilitiesTotal += $balance;
            } elseif (in_array($classification, $equityClasses, true)) {
                $equityAccountsTotal += $balance;
            }
        }

        $assetsTotal = round($assetsTotal, 2);
        $liabilitiesTotalAbs = round(abs($liabilitiesTotal), 2);
        $equityAccountsAbs = round(abs($equityAccountsTotal), 2);
        $netIncomeRounded = round($netIncome, 2);
        $equityTotal = round(abs($equityAccountsTotal - $netIncome), 2);

        return [
            'as_of' => $asOfDate,
            'assets_total' => $assetsTotal,
            'liabilities_total' => $liabilitiesTotalAbs,
            'equity_accounts_total' => $equityAccountsAbs,
            'net_income' => $netIncomeRounded,
            'equity_total' => $equityTotal,
            'liabilities_plus_equity' => $liabilitiesTotalAbs + $equityTotal,
        ];
    }

    /**
     * Helper to sum balances for specific codes/classifications combo.
     */
    public function sumByAccounts(array $accountCodes, string $startDate, string $endDate, bool $creditMinusDebit = false): float
    {
        return $this->accountingService->sumByAccountCodes($accountCodes, $startDate, $endDate, $creditMinusDebit);
    }

    /**
     * Snapshot of near-real-time revenue, expense, profit, and receivables KPIs.
     */
    public function getLiveFinancialSnapshot(): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $todayRevenue = $this->accountingService->sumByAccountTypes(['REVENUE', 'OTHER_INCOME'], $today, $today, true);
        $todayExpenses = $this->accountingService->sumByAccountClassifications([
            'OPERATING_EXPENSE',
            'NON_OPERATING_EXPENSE',
            'OTHER_EXPENSE',
            'COST_OF_SALES'
        ], $today, $today, false);

        $monthRevenue = $this->accountingService->sumByAccountTypes(['REVENUE', 'OTHER_INCOME'], $monthStart, $today, true);
        $monthExpenses = $this->accountingService->sumByAccountClassifications([
            'OPERATING_EXPENSE',
            'NON_OPERATING_EXPENSE',
            'OTHER_EXPENSE',
            'COST_OF_SALES'
        ], $monthStart, $today, false);

        $receivables = $this->getReceivablesSummary();
        $monthNet = $monthRevenue - $monthExpenses;

        return [
            'today_revenue' => round($todayRevenue, 2),
            'today_expense' => round($todayExpenses, 2),
            'today_net' => round($todayRevenue - $todayExpenses, 2),
            'month_revenue' => round($monthRevenue, 2),
            'month_expense' => round($monthExpenses, 2),
            'month_net' => round($monthNet, 2),
            'profit_margin' => $monthRevenue > 0 ? round(($monthNet / $monthRevenue) * 100, 2) : 0.0,
            'pending_receivables_total' => round($receivables['total'], 2),
            'pending_receivables_count' => (int) $receivables['count'],
            'timestamp' => date(DATE_ATOM),
        ];
    }

    /**
     * Aggregate open receivables from sales still awaiting full payment.
     */
    public function getReceivablesSummary(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) AS count, COALESCE(SUM(total_amount - amount_paid), 0) AS total
                 FROM sales
                 WHERE amount_paid < total_amount"
            );
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];

            return [
                'count' => (int) ($row['count'] ?? 0),
                'total' => isset($row['total']) ? (float) $row['total'] : 0.0,
            ];
        } catch (Exception $e) {
            return ['count' => 0, 'total' => 0.0];
        }
    }

    /**
     * Latest posted journal entries with line highlights for live feeds.
     */
    public function getRecentLedgerEntries(int $limit = 12): array
    {
        $limit = max(1, min($limit, 50));
        $statusFilter = $this->postedFilter('je');
        $entryNumberColumn = $this->hasJournalEntriesColumn('entry_number') ? 'je.entry_number' : 'NULL';
        $referenceColumn = $this->hasJournalEntriesColumn('reference_no')
            ? 'je.reference_no'
            : ($this->hasJournalEntriesColumn('reference') ? 'je.reference' : 'NULL');
        $sourceColumn = $this->hasJournalEntriesColumn('source') ? 'je.source' : 'NULL';

        $sql = "SELECT je.id,
                       {$entryNumberColumn} AS entry_number,
                       je.entry_date,
                       {$referenceColumn} AS reference_no,
                       je.description,
                       {$sourceColumn} AS source,
                       je.total_debit,
                       je.total_credit,
                       je.created_at
                FROM journal_entries je
                WHERE {$statusFilter}
                ORDER BY je.created_at DESC, je.id DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($entries)) {
            return [];
        }

        $ids = array_map(static fn ($row) => (int) $row['id'], $entries);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $lineSql = "SELECT jel.journal_entry_id,
                           a.code AS account_code,
                           a.name AS account_name,
                           jel.debit_amount,
                           jel.credit_amount
                    FROM journal_entry_lines jel
                    JOIN accounts a ON a.id = jel.account_id
                    WHERE jel.journal_entry_id IN ({$placeholders})
                    ORDER BY jel.journal_entry_id DESC, jel.id ASC";

        $lineStmt = $this->db->prepare($lineSql);
        $lineStmt->execute($ids);
        $lineRows = $lineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $lineMap = [];
        foreach ($lineRows as $line) {
            $entryId = (int) ($line['journal_entry_id'] ?? 0);
            if (!isset($lineMap[$entryId])) {
                $lineMap[$entryId] = [];
            }
            $lineMap[$entryId][] = [
                'account_code' => $line['account_code'] ?? null,
                'account_name' => $line['account_name'] ?? null,
                'debit' => isset($line['debit_amount']) ? (float) $line['debit_amount'] : 0.0,
                'credit' => isset($line['credit_amount']) ? (float) $line['credit_amount'] : 0.0,
            ];
        }

        return array_map(static function ($entry) use ($lineMap) {
            $id = (int) ($entry['id'] ?? 0);
            $lines = $lineMap[$id] ?? [];

            return [
                'id' => $id,
                'entry_number' => $entry['entry_number'] ?? null,
                'entry_date' => $entry['entry_date'] ?? null,
                'reference_no' => $entry['reference_no'] ?? null,
                'description' => $entry['description'] ?? '',
                'source' => $entry['source'] ?? null,
                'total_debit' => isset($entry['total_debit']) ? (float) $entry['total_debit'] : 0.0,
                'total_credit' => isset($entry['total_credit']) ? (float) $entry['total_credit'] : 0.0,
                'created_at' => $entry['created_at'] ?? null,
                'lines' => array_slice($lines, 0, 5),
                'line_count' => count($lines),
            ];
        }, $entries);
    }

    private function postedFilter(string $alias = 'je'): string
    {
        return $this->hasJournalEntriesColumn('status') ? "{$alias}.status = 'posted'" : '1=1';
    }

    private function hasJournalEntriesColumn(string $column): bool
    {
        if (array_key_exists($column, $this->journalEntriesColumnCache)) {
            return $this->journalEntriesColumnCache[$column];
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS column_exists
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'journal_entries'
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->journalEntriesColumnCache[$column] = !empty($row['column_exists']);

        return $this->journalEntriesColumnCache[$column];
    }
}
