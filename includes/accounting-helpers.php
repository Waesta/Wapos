<?php
/**
 * Accounting helper utilities for IFRS-compliant chart of accounts
 */

if (!function_exists('getChartOfAccountsTemplates')) {
    function getChartOfAccountsTemplates(): array
    {
        static $templates = null;

        if ($templates === null) {
            $templates = [
                '1000' => [
                    'name' => 'Cash and Cash Equivalents',
                    'type' => 'ASSET',
                    'classification' => 'CURRENT_ASSET',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 100,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 7.2',
                ],
                '1010' => [
                    'name' => 'Petty Cash',
                    'type' => 'ASSET',
                    'classification' => 'CURRENT_ASSET',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 110,
                    'parent_code' => '1000',
                    'ifrs_reference' => 'IFRS-SME 7.2',
                ],
                '1100' => [
                    'name' => 'Bank Accounts',
                    'type' => 'ASSET',
                    'classification' => 'CURRENT_ASSET',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 120,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 7.2',
                ],
                '1200' => [
                    'name' => 'Accounts Receivable',
                    'type' => 'ASSET',
                    'classification' => 'CURRENT_ASSET',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 200,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 11.13',
                ],
                '1300' => [
                    'name' => 'Inventory',
                    'type' => 'ASSET',
                    'classification' => 'CURRENT_ASSET',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 210,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 13.4',
                ],
                '1400' => [
                    'name' => 'Prepaid Expenses',
                    'type' => 'ASSET',
                    'classification' => 'CURRENT_ASSET',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 220,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 11.14',
                ],
                '1410' => [
                    'name' => 'VAT Recoverable (Input Tax)',
                    'type' => 'ASSET',
                    'classification' => 'CURRENT_ASSET',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 225,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 29.27',
                ],
                '1500' => [
                    'name' => 'Property, Plant and Equipment',
                    'type' => 'ASSET',
                    'classification' => 'NON_CURRENT_ASSET',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 300,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 17.10',
                ],
                '1600' => [
                    'name' => 'Accumulated Depreciation',
                    'type' => 'ASSET',
                    'classification' => 'CONTRA_ASSET',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 305,
                    'parent_code' => '1500',
                    'ifrs_reference' => 'IFRS-SME 17.23',
                ],
                '2000' => [
                    'name' => 'Accounts Payable',
                    'type' => 'LIABILITY',
                    'classification' => 'CURRENT_LIABILITY',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 400,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 11.17',
                ],
                '2100' => [
                    'name' => 'Sales Tax Payable',
                    'type' => 'LIABILITY',
                    'classification' => 'CURRENT_LIABILITY',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 410,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 29.12',
                ],
                '2200' => [
                    'name' => 'Accrued Expenses',
                    'type' => 'LIABILITY',
                    'classification' => 'CURRENT_LIABILITY',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 420,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 11.17',
                ],
                '2300' => [
                    'name' => 'Short-term Loans',
                    'type' => 'LIABILITY',
                    'classification' => 'CURRENT_LIABILITY',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 430,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 11.13',
                ],
                '2400' => [
                    'name' => 'Long-term Loans',
                    'type' => 'LIABILITY',
                    'classification' => 'NON_CURRENT_LIABILITY',
                    'statement_section' => 'BALANCE_SHEET',
                    'reporting_order' => 500,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 11.14',
                ],
                '3000' => [
                    'name' => "Owner's Capital",
                    'type' => 'EQUITY',
                    'classification' => 'EQUITY',
                    'statement_section' => 'EQUITY',
                    'reporting_order' => 600,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 6.3',
                ],
                '3100' => [
                    'name' => 'Retained Earnings',
                    'type' => 'EQUITY',
                    'classification' => 'EQUITY',
                    'statement_section' => 'EQUITY',
                    'reporting_order' => 610,
                    'parent_code' => '3000',
                    'ifrs_reference' => 'IFRS-SME 6.5',
                ],
                '3200' => [
                    'name' => 'Owner Drawings',
                    'type' => 'EQUITY',
                    'classification' => 'CONTRA_EQUITY',
                    'statement_section' => 'EQUITY',
                    'reporting_order' => 620,
                    'parent_code' => '3000',
                    'ifrs_reference' => 'IFRS-SME 6.7',
                ],
                '4000' => [
                    'name' => 'Sales Revenue',
                    'type' => 'REVENUE',
                    'classification' => 'REVENUE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 700,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 23.30',
                ],
                '4100' => [
                    'name' => 'Service Revenue',
                    'type' => 'REVENUE',
                    'classification' => 'REVENUE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 710,
                    'parent_code' => '4000',
                    'ifrs_reference' => 'IFRS-SME 23.30',
                ],
                '4200' => [
                    'name' => 'Other Operating Income',
                    'type' => 'OTHER_INCOME',
                    'classification' => 'OTHER_INCOME',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 720,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 23.30',
                ],
                '4500' => [
                    'name' => 'Sales Returns and Allowances',
                    'type' => 'CONTRA_REVENUE',
                    'classification' => 'CONTRA_REVENUE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 730,
                    'parent_code' => '4000',
                    'ifrs_reference' => 'IFRS-SME 23.31',
                ],
                '4510' => [
                    'name' => 'Sales Discounts',
                    'type' => 'CONTRA_REVENUE',
                    'classification' => 'CONTRA_REVENUE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 735,
                    'parent_code' => '4000',
                    'ifrs_reference' => 'IFRS-SME 23.31',
                ],
                '5000' => [
                    'name' => 'Cost of Goods Sold',
                    'type' => 'EXPENSE',
                    'classification' => 'COST_OF_SALES',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 800,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 13.19',
                ],
                '5100' => [
                    'name' => 'Direct Labour',
                    'type' => 'EXPENSE',
                    'classification' => 'COST_OF_SALES',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 810,
                    'parent_code' => '5000',
                    'ifrs_reference' => 'IFRS-SME 13.19',
                ],
                '5200' => [
                    'name' => 'Freight Inwards',
                    'type' => 'EXPENSE',
                    'classification' => 'COST_OF_SALES',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 820,
                    'parent_code' => '5000',
                    'ifrs_reference' => 'IFRS-SME 13.19',
                ],
                '6000' => [
                    'name' => 'Operating Expenses',
                    'type' => 'EXPENSE',
                    'classification' => 'OPERATING_EXPENSE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 900,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 2.52',
                ],
                '6100' => [
                    'name' => 'Salaries and Wages',
                    'type' => 'EXPENSE',
                    'classification' => 'OPERATING_EXPENSE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 910,
                    'parent_code' => '6000',
                    'ifrs_reference' => 'IFRS-SME 2.52',
                ],
                '6200' => [
                    'name' => 'Rent Expense',
                    'type' => 'EXPENSE',
                    'classification' => 'OPERATING_EXPENSE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 920,
                    'parent_code' => '6000',
                    'ifrs_reference' => 'IFRS-SME 2.52',
                ],
                '6300' => [
                    'name' => 'Utilities Expense',
                    'type' => 'EXPENSE',
                    'classification' => 'OPERATING_EXPENSE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 930,
                    'parent_code' => '6000',
                    'ifrs_reference' => 'IFRS-SME 2.52',
                ],
                '6400' => [
                    'name' => 'Marketing Expense',
                    'type' => 'EXPENSE',
                    'classification' => 'OPERATING_EXPENSE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 940,
                    'parent_code' => '6000',
                    'ifrs_reference' => 'IFRS-SME 2.52',
                ],
                '6500' => [
                    'name' => 'Depreciation Expense',
                    'type' => 'EXPENSE',
                    'classification' => 'OPERATING_EXPENSE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 950,
                    'parent_code' => '6000',
                    'ifrs_reference' => 'IFRS-SME 17.23',
                ],
                '6600' => [
                    'name' => 'Finance Costs',
                    'type' => 'EXPENSE',
                    'classification' => 'NON_OPERATING_EXPENSE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 960,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 25.3',
                ],
                '6700' => [
                    'name' => 'Other Expenses',
                    'type' => 'EXPENSE',
                    'classification' => 'OTHER_EXPENSE',
                    'statement_section' => 'PROFIT_AND_LOSS',
                    'reporting_order' => 970,
                    'parent_code' => null,
                    'ifrs_reference' => 'IFRS-SME 2.52',
                ],
            ];
        }

        return $templates;
    }
}

if (!function_exists('ensureAccountIdByCode')) {
    function ensureAccountIdByCode(Database $db, string $code): int
    {
        $account = $db->fetchOne("SELECT id FROM accounts WHERE code = ?", [$code]);
        if ($account && isset($account['id'])) {
            return (int) $account['id'];
        }

        $templates = getChartOfAccountsTemplates();
        if (!isset($templates[$code])) {
            throw new Exception("Account code {$code} is not defined in the chart of accounts. Please configure it before posting.");
        }

        $template = $templates[$code];

        $db->insert('accounts', [
            'code' => $code,
            'name' => $template['name'],
            'type' => $template['type'],
            'classification' => $template['classification'],
            'statement_section' => $template['statement_section'],
            'reporting_order' => $template['reporting_order'],
            'parent_code' => $template['parent_code'],
            'ifrs_reference' => $template['ifrs_reference'],
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $account = $db->fetchOne("SELECT id FROM accounts WHERE code = ?", [$code]);
        if (!$account || !isset($account['id'])) {
            throw new Exception("Failed to ensure account {$code} exists. Please review the chart of accounts configuration.");
        }

        return (int) $account['id'];
    }
}
