<?php
require_once 'includes/bootstrap.php';

$auth->requireRole(['admin', 'accountant', 'developer']);

$db = Database::getInstance();
$taxRateSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1", ['tax_rate']);
$defaultTaxRateRaw = $taxRateSetting['setting_value'] ?? null;
$defaultTaxRate = is_numeric($defaultTaxRateRaw) ? (float)$defaultTaxRateRaw : null;

$pageTitle = 'Chart of Accounts';
$currencySymbol = getCurrencySymbol();
$csrfToken = generateCSRFToken();

include 'includes/header.php';
?>

<style>
    .wizard-step { display: none; }
    .wizard-step.active { display: block; }
    .wizard-progress {
        display: flex;
        gap: 1rem;
        justify-content: space-between;
    }
    .wizard-step-indicator {
        flex: 1 1 0;
        font-size: 0.9rem;
        color: #6c757d;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid rgba(0,0,0,0.08);
        text-align: center;
    }
    .wizard-step-indicator.active {
        color: var(--bs-primary);
        border-bottom-color: var(--bs-primary);
        font-weight: 600;
    }
    .wizard-step-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        margin-right: 0.4rem;
        border-radius: 50%;
        background: rgba(var(--bs-primary-rgb, 13,110,253), 0.1);
        color: var(--bs-primary);
        font-weight: 600;
    }
    .revenue-wizard-option {
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 0.75rem;
        background: #fff;
        transition: all 0.2s ease-in-out;
    }
    .revenue-wizard-option:hover,
    .revenue-wizard-option:focus {
        border-color: var(--bs-primary);
        box-shadow: 0 0.75rem 1.5rem rgba(13, 110, 253, 0.08);
        transform: translateY(-2px);
    }
    .revenue-wizard-option.active {
        border-color: var(--bs-primary);
        background: rgba(var(--bs-primary-rgb, 13,110,253), 0.05);
        box-shadow: 0 0.9rem 1.5rem rgba(13, 110, 253, 0.18);
    }
    .wizard-summary dl { margin-bottom: 0; }
    .wizard-summary dt { color: #6c757d; font-size: 0.85rem; }
    .wizard-summary dd { font-size: 0.95rem; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-journal-richtext me-2"></i>Chart of Accounts</h4>
        <p class="text-muted small mb-0">Maintain your IFRS-aligned ledger structure. Only active accounts appear in posting screens.</p>
    </div>
    <div class="btn-group">
        <button class="btn btn-outline-secondary" id="refreshTableBtn">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
        <button class="btn btn-outline-primary" id="guidedWizardBtn">
            <i class="bi bi-stars me-1"></i>Add Revenue Stream
        </button>
        <button class="btn btn-primary" id="addAccountBtn">
            <i class="bi bi-plus-circle me-1"></i>New Account
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-uppercase text-muted fw-bold">Active Accounts</h6>
                <h3 class="fw-bold" id="activeAccountsCount">0</h3>
                <p class="text-muted small mb-0">Total accounts ready for posting.</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-uppercase text-muted fw-bold">Revenue Streams</h6>
                <h3 class="fw-bold" id="revenueAccountsCount">0</h3>
                <p class="text-muted small mb-0">Codes classified under revenue/other income.</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-uppercase text-muted fw-bold">Expense Buckets</h6>
                <h3 class="fw-bold" id="expenseAccountsCount">0</h3>
                <p class="text-muted small mb-0">Operating, non-operating and cost of sales accounts.</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-uppercase text-muted fw-bold">Last Updated</h6>
                <h3 class="fw-bold" id="lastUpdatedText">—</h3>
                <p class="text-muted small mb-0">Reflects the latest modification timestamp.</p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="accountSearch" placeholder="Search by code, name or IFRS reference">
            </div>
            <select class="form-select form-select-sm" id="typeFilter" style="max-width: 200px;">
                <option value="">All types</option>
            </select>
            <select class="form-select form-select-sm" id="classificationFilter" style="max-width: 220px;">
                <option value="">All classifications</option>
            </select>
        </div>
        <span class="badge bg-light text-muted">
            <i class="bi bi-info-circle me-1"></i>Click a row to edit the account details.
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 540px;">
            <table class="table table-hover mb-0" id="accountsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 120px;">Code</th>
                        <th>Name</th>
                        <th style="width: 160px;">Type</th>
                        <th style="width: 200px;">Classification</th>
                        <th style="width: 140px;">Statement</th>
                        <th style="width: 120px;">Parent</th>
                        <th style="width: 120px;">Order</th>
                        <th style="width: 90px;">Status</th>
                        <th style="width: 60px;" class="text-end">&nbsp;</th>
                    </tr>
                </thead>
                <tbody id="accountsTableBody">
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2"></div>Loading accounts...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Account Modal -->
<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="accountForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="save_account">
                <input type="hidden" name="id" id="accountId">
                <div class="modal-header">
                    <h5 class="modal-title" id="accountModalTitle">New Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Account Code *</label>
                            <input type="text" class="form-control" name="code" id="accountCode" required maxlength="20">
                            <div class="form-text">3-20 characters (letters, digits, dash).</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Account Name *</label>
                            <input type="text" class="form-control" name="name" id="accountName" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account Type *</label>
                            <select class="form-select" name="type" id="accountType" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Classification *</label>
                            <select class="form-select" name="classification" id="accountClassification" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Statement Section *</label>
                            <select class="form-select" name="statement_section" id="accountStatement" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Parent Account</label>
                            <select class="form-select" name="parent_code" id="accountParent"></select>
                            <div class="form-text">Optional: nest under an existing code.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reporting Order</label>
                            <input type="number" class="form-control" name="reporting_order" id="accountOrder" min="0" step="1">
                            <div class="form-text">Lower numbers appear first in reports.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">IFRS Reference</label>
                            <input type="text" class="form-control" name="ifrs_reference" id="accountIFRS" maxlength="50">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Template Suggestions</label>
                            <select class="form-select" id="templateSelect">
                                <option value="">Choose template...</option>
                            </select>
                            <div class="form-text">Select a template to auto-fill name and mappings.</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="accountActive" name="is_active" checked>
                                <label class="form-check-label" for="accountActive">Account is active (available for postings)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info d-flex align-items-start gap-2 mb-0" id="guidedHint" style="display:none;">
                                <i class="bi bi-lightbulb"></i>
                                <div>
                                    <strong>Guided Workflow:</strong> Complete the highlighted fields to create the new revenue stream. Tax settings can be configured later under System Settings.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="accountSubmitBtn">
                        <span class="default-text"><i class="bi bi-save me-2"></i>Save Account</span>
                        <span class="saving-text" style="display:none;"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Guided Revenue Stream Wizard -->
<div class="modal fade" id="revenueWizardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-stars me-2"></i>Guided Revenue Stream Setup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="wizard-progress mb-4">
                    <div class="wizard-step-indicator" data-step-indicator="1">
                        <span class="wizard-step-number">1</span>Choose Template
                    </div>
                    <div class="wizard-step-indicator" data-step-indicator="2">
                        <span class="wizard-step-number">2</span>Configure Details
                    </div>
                    <div class="wizard-step-indicator" data-step-indicator="3">
                        <span class="wizard-step-number">3</span>Tax & Summary
                    </div>
                </div>

                <div class="wizard-step" data-step="1">
                    <p class="text-muted">Pick the revenue stream that best matches what you want to add. We’ll auto-fill the accounting treatment for you.</p>
                    <div class="row g-3" id="wizardTemplateContainer"></div>
                </div>

                <div class="wizard-step" data-step="2">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Revenue Stream Name *</label>
                            <input type="text" class="form-control" id="wizardNameInput" placeholder="e.g. Corporate Catering" required>
                            <div class="form-text">Appears on reports and invoices.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Code *</label>
                            <input type="text" class="form-control" id="wizardCodeInput" maxlength="20" required>
                            <div class="form-text">Automatically suggested; you can adjust if needed.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Classification *</label>
                            <select class="form-select" id="wizardClassificationSelect">
                                <option value="REVENUE">Revenue (operating)</option>
                                <option value="OTHER_INCOME">Other Income</option>
                                <option value="CONTRA_REVENUE">Contra Revenue / Discount</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parent Account</label>
                            <select class="form-select" id="wizardParentSelect"></select>
                            <div class="form-text">Keep under 4000-series unless advised by your accountant.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description (optional)</label>
                            <textarea class="form-control" id="wizardDescriptionInput" rows="2" placeholder="Add any internal notes or usage context"></textarea>
                        </div>
                    </div>
                </div>

                <div class="wizard-step" data-step="3">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-muted fw-semibold">Tax Behaviour</h6>
                            <div class="list-group" id="wizardTaxOptions">
                                <label class="list-group-item d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1" type="radio" name="wizardTaxBehaviour" value="standard" checked>
                                    <div>
                                        <div class="fw-semibold">Standard VAT</div>
                                        <small class="text-muted">Applies the default tax rate to this revenue stream.</small>
                                    </div>
                                </label>
                                <label class="list-group-item d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1" type="radio" name="wizardTaxBehaviour" value="zero_rated">
                                    <div>
                                        <div class="fw-semibold">Zero-Rated</div>
                                        <small class="text-muted">Recorded for reporting but charged at 0% tax.</small>
                                    </div>
                                </label>
                                <label class="list-group-item d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1" type="radio" name="wizardTaxBehaviour" value="exempt">
                                    <div>
                                        <div class="fw-semibold">Exempt</div>
                                        <small class="text-muted">No VAT applied or reported for this revenue.</small>
                                    </div>
                                </label>
                                <label class="list-group-item d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1" type="radio" name="wizardTaxBehaviour" value="custom">
                                    <div>
                                        <div class="fw-semibold">Custom Rate</div>
                                        <small class="text-muted">Specify a unique tax percentage below.</small>
                                        <div class="mt-2">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">%</span>
                                                <input type="number" class="form-control" id="wizardCustomTaxInput" min="0" step="0.01" placeholder="15.00">
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-muted fw-semibold">Summary</h6>
                            <div class="wizard-summary border rounded-3 p-3 bg-light">
                                <dl class="row mb-2">
                                    <dt class="col-5">Revenue Stream</dt>
                                    <dd class="col-7" id="summaryName">—</dd>
                                </dl>
                                <dl class="row mb-2">
                                    <dt class="col-5">Account Code</dt>
                                    <dd class="col-7" id="summaryCode">—</dd>
                                </dl>
                                <dl class="row mb-2">
                                    <dt class="col-5">Classification</dt>
                                    <dd class="col-7" id="summaryClassification">—</dd>
                                </dl>
                                <dl class="row mb-2">
                                    <dt class="col-5">Parent</dt>
                                    <dd class="col-7" id="summaryParent">—</dd>
                                </dl>
                                <dl class="row">
                                    <dt class="col-5">Tax Treatment</dt>
                                    <dd class="col-7" id="summaryTax">—</dd>
                                </dl>
                                <div class="alert alert-info small mb-0 mt-3" id="summaryGuidance">
                                    <i class="bi bi-info-circle me-2"></i>We will create the account and record the tax preference in System Settings for reference.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="wizardBackBtn">Back</button>
                <div class="flex-grow-1"></div>
                <button type="button" class="btn btn-outline-primary" id="wizardNextBtn">Next</button>
                <button type="button" class="btn btn-primary" id="wizardFinishBtn">
                    <span class="default-text">Create Revenue Stream</span>
                    <span class="saving-text" style="display:none;"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Account Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="statusModalBody"></p>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>Deactivated accounts remain in historical reports but are hidden from new transactions.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStatusChangeBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
const apiUrl = 'api/coa-management.php';
const csrfToken = <?= json_encode($csrfToken) ?>;
const defaultTaxRate = <?= $defaultTaxRate !== null ? json_encode($defaultTaxRate) : 'null' ?>;
let metadata = { types: [], classifications: [], statement_sections: [], templates: {}, parents: [] };
let accounts = [];
let accountModalInstance = null;
let statusModalInstance = null;
let pendingStatusAccount = null;
let guidedMode = false;
let revenueWizardModalInstance = null;
let wizardCurrentStep = 1;
let wizardSelectedTemplateCode = null;
let wizardTemplateTouched = false;

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => Array.from(document.querySelectorAll(selector));

let wizardState = {};

function resetWizardState() {
    const primaryParent = (metadata.parents || []).find(parent => parent.code === '4000');
    wizardState = {
        templateCode: null,
        name: '',
        code: '',
        type: 'REVENUE',
        classification: 'REVENUE',
        statementSection: 'PROFIT_AND_LOSS',
        parentCode: primaryParent ? primaryParent.code : '',
        reportingOrder: '',
        ifrsReference: '',
        description: '',
        taxBehaviour: 'standard',
        customTaxRate: '',
    };
    wizardSelectedTemplateCode = null;
    wizardTemplateTouched = false;
}

function setWizardStep(step) {
    wizardCurrentStep = Math.min(Math.max(step, 1), 3);

    $$('.wizard-step').forEach((section) => {
        section.classList.toggle('active', Number(section.dataset.step) === wizardCurrentStep);
    });

    $$('.wizard-step-indicator').forEach((indicator) => {
        const indicatorStep = Number(indicator.dataset.stepIndicator);
        indicator.classList.toggle('active', indicatorStep === wizardCurrentStep);
        indicator.classList.toggle('text-muted', indicatorStep !== wizardCurrentStep);
    });

    const backBtn = $('#wizardBackBtn');
    const nextBtn = $('#wizardNextBtn');
    const finishBtn = $('#wizardFinishBtn');

    if (backBtn) {
        backBtn.style.visibility = wizardCurrentStep === 1 ? 'hidden' : 'visible';
    }
    if (nextBtn) {
        nextBtn.style.display = wizardCurrentStep === 3 ? 'none' : '';
    }
    if (finishBtn) {
        finishBtn.style.display = wizardCurrentStep === 3 ? '' : 'none';
    }

    updateWizardSummary();
}

function renderWizardTemplateCards() {
    const container = $('#wizardTemplateContainer');
    if (!container) {
        return;
    }

    const revenueTemplates = Object.entries(metadata.templates || {}).filter(([, template]) => {
        if (!template) {
            return false;
        }
        return ['REVENUE', 'OTHER_INCOME', 'CONTRA_REVENUE'].includes(template.classification ?? template.type ?? '');
    });

    if (!revenueTemplates.length) {
        container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>No revenue templates found. You can still proceed with a custom revenue stream.
                </div>
            </div>`;
        return;
    }

    const cards = revenueTemplates.map(([code, template]) => {
        const classificationLabel = formatLabel(template.classification ?? template.type ?? 'REVENUE');
        const statementLabel = formatLabel(template.statement_section ?? 'PROFIT_AND_LOSS');
        return `
            <div class="col-md-6">
                <button type="button" class="revenue-wizard-option w-100 p-3 text-start" data-template-code="${code}">
                    <div class="d-flex align-items-center mb-2">
                        <div class="wizard-step-number flex-shrink-0 me-2"><i class="bi bi-stars"></i></div>
                        <div>
                            <div class="fw-semibold">${escapeHtml(template.name)}</div>
                            <small class="text-muted">${classificationLabel} · ${statementLabel}</small>
                        </div>
                    </div>
                    ${template.ifrs_reference ? `<p class="mb-0 small text-muted">IFRS reference: ${escapeHtml(template.ifrs_reference)}</p>` : ''}
                </button>
            </div>`;
    });

    cards.push(`
        <div class="col-md-6">
            <button type="button" class="revenue-wizard-option w-100 p-3 text-start" data-template-code="__manual__">
                <div class="d-flex align-items-center mb-2">
                    <div class="wizard-step-number flex-shrink-0 me-2"><i class="bi bi-pencil"></i></div>
                    <div>
                        <div class="fw-semibold">Custom revenue stream</div>
                        <small class="text-muted">Start from a blank template.</small>
                    </div>
                </div>
                <p class="mb-0 small text-muted">Ideal when the revenue structure is unique or specialised.</p>
            </button>
        </div>`);

    container.innerHTML = cards.join('');

    $$('#wizardTemplateContainer .revenue-wizard-option').forEach((button) => {
        button.addEventListener('click', () => {
            selectWizardTemplate(button.dataset.templateCode || null);
        });
    });
}

function populateWizardParentOptions() {
    const parentSelect = $('#wizardParentSelect');
    if (!parentSelect) {
        return;
    }

    const options = ['<option value="">None</option>'];
    (metadata.parents || []).forEach((parent) => {
        options.push(`<option value="${parent.code}">${parent.code} · ${escapeHtml(parent.name)}</option>`);
    });
    parentSelect.innerHTML = options.join('');

    if (wizardState.parentCode) {
        parentSelect.value = wizardState.parentCode;
    }
}

function selectWizardTemplate(code) {
    wizardSelectedTemplateCode = code && code !== '__manual__' ? code : null;
    wizardTemplateTouched = true;

    $$('#wizardTemplateContainer .revenue-wizard-option').forEach((button) => {
        const matches = button.dataset.templateCode === code;
        button.classList.toggle('active', matches);
    });

    const template = wizardSelectedTemplateCode && metadata.templates ? metadata.templates[wizardSelectedTemplateCode] : null;

    if (template) {
        wizardState.templateCode = wizardSelectedTemplateCode;
        wizardState.name = template.name || '';
        wizardState.type = template.type ?? 'REVENUE';
        wizardState.classification = template.classification ?? 'REVENUE';
        wizardState.statementSection = template.statement_section ?? 'PROFIT_AND_LOSS';
        wizardState.parentCode = template.parent_code ?? (wizardState.parentCode || '4000');
        wizardState.reportingOrder = template.reporting_order ?? '';
        wizardState.ifrsReference = template.ifrs_reference ?? '';
    } else {
        wizardState.templateCode = null;
        wizardState.name = '';
        wizardState.type = 'REVENUE';
        wizardState.classification = 'REVENUE';
        wizardState.statementSection = 'PROFIT_AND_LOSS';
        wizardState.parentCode = wizardState.parentCode || '4000';
        wizardState.reportingOrder = '';
        wizardState.ifrsReference = '';
    }

    const suggestedCode = suggestNextRevenueCode();
    wizardState.code = template && template.code ? template.code : suggestedCode;

    $('#wizardNameInput').value = wizardState.name;
    $('#wizardCodeInput').value = wizardState.code;
    $('#wizardClassificationSelect').value = wizardState.classification;
    $('#wizardParentSelect').value = wizardState.parentCode ?? '';
    $('#wizardDescriptionInput').value = wizardState.description;
    $('#wizardCustomTaxInput').value = wizardState.customTaxRate;

    updateWizardSummary();
}

function updateWizardSummary() {
    const nameEl = $('#summaryName');
    const codeEl = $('#summaryCode');
    const classificationEl = $('#summaryClassification');
    const parentEl = $('#summaryParent');
    const taxEl = $('#summaryTax');

    if (nameEl) nameEl.textContent = wizardState.name || '—';
    if (codeEl) codeEl.textContent = wizardState.code || '—';
    if (classificationEl) classificationEl.textContent = formatLabel(wizardState.classification);

    if (parentEl) {
        if (!wizardState.parentCode) {
            parentEl.textContent = '—';
        } else {
            const parent = (metadata.parents || []).find((item) => item.code === wizardState.parentCode);
            parentEl.textContent = parent ? `${parent.code} · ${parent.name}` : wizardState.parentCode;
        }
    }

    if (taxEl) {
        taxEl.textContent = formatTaxBehaviourSummary();
    }

    const customInput = $('#wizardCustomTaxInput');
    if (customInput) {
        customInput.disabled = wizardState.taxBehaviour !== 'custom';
        customInput.parentElement?.classList.toggle('d-none', wizardState.taxBehaviour !== 'custom');
    }
}

function formatTaxBehaviourSummary() {
    switch (wizardState.taxBehaviour) {
        case 'standard':
            return defaultTaxRate !== null
                ? `Standard VAT (${Number(defaultTaxRate).toFixed(2)}%)`
                : 'Standard VAT (system default)';
        case 'zero_rated':
            return 'Zero-rated (report as 0%)';
        case 'exempt':
            return 'Exempt (no VAT reporting)';
        case 'custom':
            if (wizardState.customTaxRate !== '') {
                return `Custom VAT (${parseFloat(wizardState.customTaxRate).toFixed(2)}%)`;
            }
            return 'Custom VAT (specify percentage)';
        default:
            return '—';
    }
}

function syncWizardFormState() {
    const nameInput = $('#wizardNameInput');
    const codeInput = $('#wizardCodeInput');
    const classificationSelect = $('#wizardClassificationSelect');
    const parentSelect = $('#wizardParentSelect');
    const descriptionInput = $('#wizardDescriptionInput');
    const customTaxInput = $('#wizardCustomTaxInput');

    if (nameInput) {
        wizardState.name = nameInput.value.trim();
    }
    if (codeInput) {
        wizardState.code = codeInput.value.trim().toUpperCase();
        codeInput.value = wizardState.code;
    }
    if (classificationSelect) {
        wizardState.classification = classificationSelect.value;
        if (wizardState.classification === 'OTHER_INCOME') {
            wizardState.type = 'OTHER_INCOME';
        } else if (wizardState.classification === 'CONTRA_REVENUE') {
            wizardState.type = 'CONTRA_REVENUE';
        } else {
            wizardState.type = 'REVENUE';
        }
    }
    if (parentSelect) {
        wizardState.parentCode = parentSelect.value || '';
    }
    if (descriptionInput) {
        wizardState.description = descriptionInput.value.trim();
    }
    if (customTaxInput) {
        wizardState.customTaxRate = customTaxInput.value.trim();
    }
}

function validateWizardStep(step) {
    syncWizardFormState();

    if (step === 1) {
        if (!wizardTemplateTouched) {
            showToast('Select a template or choose custom to continue.', 'warning');
            return false;
        }
        return true;
    }

    if (step === 2) {
        const codePattern = /^[A-Z0-9\-]{3,20}$/;
        if (!wizardState.name.trim()) {
            showToast('Provide a revenue stream name.', 'warning');
            $('#wizardNameInput').focus();
            return false;
        }
        if (!wizardState.code.trim() || !codePattern.test(wizardState.code.trim())) {
            showToast('Account code must be 3-20 characters (letters, numbers, dash).', 'warning');
            $('#wizardCodeInput').focus();
            return false;
        }
        if (accounts.some((account) => account.code === wizardState.code.trim())) {
            showToast('That account code already exists. Please choose a different code.', 'warning');
            $('#wizardCodeInput').focus();
            return false;
        }
        return true;
    }

    if (step === 3) {
        if (wizardState.taxBehaviour === 'custom') {
            const normalized = normalizeDecimalInput(wizardState.customTaxRate);
            if (normalized === '' || !isFinite(Number(normalized))) {
                showToast('Provide a numeric custom tax rate.', 'warning');
                $('#wizardCustomTaxInput').focus();
                return false;
            }
            if (Number(normalized) < 0) {
                showToast('Custom tax rate cannot be negative.', 'warning');
                $('#wizardCustomTaxInput').focus();
                return false;
            }
        }
        return true;
    }

    return true;
}

function openRevenueWizard() {
    resetWizardState();
    renderWizardTemplateCards();
    populateWizardParentOptions();
    const nameInput = $('#wizardNameInput');
    if (nameInput) {
        nameInput.value = '';
    }
    $('#wizardCodeInput').value = suggestNextRevenueCode();
    wizardState.code = $('#wizardCodeInput').value;
    wizardState.taxBehaviour = 'standard';
    wizardState.customTaxRate = '';

    revenueWizardModalInstance = revenueWizardModalInstance || new bootstrap.Modal(document.getElementById('revenueWizardModal'));
    setWizardStep(1);
    updateWizardSummary();
    revenueWizardModalInstance.show();
}

function submitRevenueWizard() {
    if (!validateWizardStep(3)) {
        return;
    }

    const finishBtn = $('#wizardFinishBtn');
    if (!finishBtn) {
        return;
    }

    finishBtn.disabled = true;
    finishBtn.querySelector('.default-text').style.display = 'none';
    finishBtn.querySelector('.saving-text').style.display = '';

    const payload = {
        action: 'guided_revenue_stream',
        csrf_token: csrfToken,
        template_code: wizardState.templateCode,
        name: wizardState.name,
        code: wizardState.code,
        type: wizardState.type,
        classification: wizardState.classification,
        statement_section: wizardState.statementSection,
        reporting_order: wizardState.reportingOrder,
        parent_code: wizardState.parentCode,
        ifrs_reference: wizardState.ifrsReference,
        description: wizardState.description,
        tax_behaviour: wizardState.taxBehaviour,
        custom_tax_rate: wizardState.taxBehaviour === 'custom' ? normalizeDecimalInput(wizardState.customTaxRate) : null,
    };

    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    })
        .then(async (response) => {
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to create revenue stream');
            }
            return data;
        })
        .then((data) => {
            const savedAccount = data.account;
            const existingIndex = accounts.findIndex((account) => account.id === savedAccount.id);
            if (existingIndex >= 0) {
                accounts[existingIndex] = savedAccount;
            } else {
                accounts.push(savedAccount);
            }

            if (savedAccount && savedAccount.is_active) {
                const parentExists = (metadata.parents || []).some((parent) => parent.code === savedAccount.code);
                if (!parentExists) {
                    metadata.parents = metadata.parents || [];
                    metadata.parents.push({ code: savedAccount.code, name: savedAccount.name });
                    metadata.parents.sort((a, b) => a.code.localeCompare(b.code));
                }
            }

            populateTemplateOptions();
            populateWizardParentOptions();
            renderAccountsTable();
            updateSummaryCards();

            showToast('Revenue stream created successfully. Tax preference saved in System Settings.');
            revenueWizardModalInstance.hide();
        })
        .catch((error) => {
            console.error(error);
            showToast(error.message, 'danger');
        })
        .finally(() => {
            finishBtn.disabled = false;
            finishBtn.querySelector('.default-text').style.display = '';
            finishBtn.querySelector('.saving-text').style.display = 'none';
        });
}

function showToast(message, type = 'success') {
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
    toastEl.style.zIndex = 1080;
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
    document.body.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 3200 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

function normalizeDecimalInput(value) {
    if (value == null) {
        return '';
    }
    if (typeof value === 'number') {
        return String(value);
    }
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed === '') {
            return '';
        }
        const normalized = trimmed.replace(/,/g, '');
        if (normalized === '') {
            return '';
        }
        return normalized;
    }
    return '';
}

async function fetchAccounts(showToastOnError = true) {
    try {
        $('#accountsTableBody').innerHTML = `
            <tr><td colspan="9" class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div>Refreshing chart of accounts...
            </td></tr>`;

        const response = await fetch(apiUrl, { credentials: 'same-origin' });
        if (!response.ok) {
            throw new Error(`Failed to load accounts (${response.status})`);
        }
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Unable to load accounts');
        }
        metadata = data.metadata || metadata;
        accounts = data.accounts || [];
        populateFilters();
        populateTemplateOptions();
        populateWizardParentOptions();
        renderAccountsTable();
        updateSummaryCards();
    } catch (error) {
        console.error(error);
        $('#accountsTableBody').innerHTML = `
            <tr><td colspan="9" class="text-center py-4 text-danger">${error.message}</td></tr>`;
        if (showToastOnError) {
            showToast(error.message, 'danger');
        }
    }
}

function populateFilters() {
    const typeFilter = $('#typeFilter');
    const classificationFilter = $('#classificationFilter');

    const currentType = typeFilter.value;
    const currentClassification = classificationFilter.value;

    typeFilter.innerHTML = '<option value="">All types</option>' + (metadata.types || []).map(option => `<option value="${option.value}">${option.label}</option>`).join('');
    classificationFilter.innerHTML = '<option value="">All classifications</option>' + (metadata.classifications || []).map(option => `<option value="${option.value}">${option.label}</option>`).join('');

    typeFilter.value = currentType;
    classificationFilter.value = currentClassification;
}

function populateTemplateOptions() {
    const templateSelect = $('#templateSelect');
    const templates = metadata.templates || {};

    const options = ['<option value="">Choose template...</option>'];
    Object.entries(templates).forEach(([code, template]) => {
        options.push(`<option value="${code}">${code} · ${template.name}</option>`);
    });
    templateSelect.innerHTML = options.join('');

    const parentSelect = $('#accountParent');
    const parentOptions = ['<option value="">None</option>'];
    (metadata.parents || []).forEach(parent => {
        parentOptions.push(`<option value="${parent.code}">${parent.code} · ${parent.name}</option>`);
    });
    parentSelect.innerHTML = parentOptions.join('');

    const typeSelect = $('#accountType');
    typeSelect.innerHTML = (metadata.types || []).map(option => `<option value="${option.value}">${option.label}</option>`).join('');

    const classificationSelect = $('#accountClassification');
    classificationSelect.innerHTML = (metadata.classifications || []).map(option => `<option value="${option.value}">${option.label}</option>`).join('');

    const statementSelect = $('#accountStatement');
    statementSelect.innerHTML = (metadata.statement_sections || []).map(option => `<option value="${option.value}">${option.label}</option>`).join('');
}

function renderAccountsTable() {
    const body = $('#accountsTableBody');
    const search = $('#accountSearch').value.trim().toLowerCase();
    const typeFilter = $('#typeFilter').value;
    const classificationFilter = $('#classificationFilter').value;

    const filtered = accounts.filter(account => {
        if (typeFilter && account.type !== typeFilter) return false;
        if (classificationFilter && account.classification !== classificationFilter) return false;
        if (!search) return true;
        return [account.code, account.name, account.ifrs_reference, account.parent_code]
            .filter(Boolean)
            .some(value => value.toLowerCase().includes(search));
    });

    if (!filtered.length) {
        body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No accounts match your filters.</td></tr>';
        return;
    }

    body.innerHTML = filtered.map(account => {
        const badgeClass = account.is_active ? 'bg-success' : 'bg-secondary';
        const statusText = account.is_active ? 'Active' : 'Inactive';
        const parent = account.parent_code ? account.parent_code : '—';
        const reportingOrder = typeof account.reporting_order === 'number' ? account.reporting_order : 0;
        const statementLabel = formatLabel(account.statement_section);
        const typeLabel = formatLabel(account.type);
        const classificationLabel = formatLabel(account.classification);

        return `
            <tr data-id="${account.id}" class="account-row">
                <td><strong>${account.code}</strong></td>
                <td>${escapeHtml(account.name)}</td>
                <td><span class="badge bg-light text-dark">${typeLabel}</span></td>
                <td><span class="badge bg-light text-dark">${classificationLabel}</span></td>
                <td>${statementLabel}</td>
                <td>${parent}</td>
                <td>${reportingOrder}</td>
                <td><span class="badge ${badgeClass}">${statusText}</span></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-${account.is_active ? 'secondary' : 'success'} toggle-status-btn" data-id="${account.id}">
                        ${account.is_active ? '<i class="bi bi-pause"></i>' : '<i class="bi bi-play"></i>'}
                    </button>
                </td>
            </tr>`;
    }).join('');

    $$('#accountsTableBody .account-row').forEach(row => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('.toggle-status-btn')) {
                return;
            }
            const accountId = Number(row.getAttribute('data-id'));
            const account = accounts.find(acc => acc.id === accountId);
            openAccountModal(account);
        });
    });

    $$('#accountsTableBody .toggle-status-btn').forEach(button => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const accountId = Number(button.getAttribute('data-id'));
            const account = accounts.find(acc => acc.id === accountId);
            promptStatusToggle(account);
        });
    });
}

function updateSummaryCards() {
    const activeCount = accounts.filter(acc => acc.is_active).length;
    const revenueCount = accounts.filter(acc => acc.classification === 'REVENUE' || acc.classification === 'OTHER_INCOME' || acc.classification === 'CONTRA_REVENUE').length;
    const expenseCount = accounts.filter(acc => ['OPERATING_EXPENSE', 'NON_OPERATING_EXPENSE', 'OTHER_EXPENSE', 'COST_OF_SALES'].includes(acc.classification)).length;
    const lastUpdated = accounts.reduce((latest, acc) => {
        const updated = acc.updated_at ? new Date(acc.updated_at) : null;
        if (!updated) return latest;
        return (!latest || updated > latest) ? updated : latest;
    }, null);

    $('#activeAccountsCount').textContent = activeCount.toLocaleString();
    $('#revenueAccountsCount').textContent = revenueCount.toLocaleString();
    $('#expenseAccountsCount').textContent = expenseCount.toLocaleString();
    $('#lastUpdatedText').textContent = lastUpdated ? lastUpdated.toLocaleString() : '—';
}

function openAccountModal(account = null, options = {}) {
    guidedMode = Boolean(options.guided);
    $('#guidedHint').style.display = guidedMode ? '' : 'none';

    $('#accountModalTitle').textContent = account ? `Edit Account · ${account.code}` : 'New Account';
    $('#accountSubmitBtn .default-text').style.display = '';
    $('#accountSubmitBtn .saving-text').style.display = 'none';
    $('#accountSubmitBtn').disabled = false;

    $('#accountId').value = account ? account.id : '';
    $('#accountCode').value = account ? account.code : (options.prefill?.code || '');
    $('#accountCode').readOnly = Boolean(account); // prevent changing code on edit to avoid ledger issues
    $('#accountName').value = account ? account.name : (options.prefill?.name || '');
    $('#accountType').value = account ? account.type : (options.prefill?.type || 'REVENUE');
    $('#accountClassification').value = account ? account.classification : (options.prefill?.classification || 'REVENUE');
    $('#accountStatement').value = account ? account.statement_section : (options.prefill?.statement_section || 'PROFIT_AND_LOSS');
    $('#accountParent').value = account ? (account.parent_code || '') : (options.prefill?.parent_code || '');
    $('#accountOrder').value = account ? account.reporting_order : (options.prefill?.reporting_order ?? '');
    $('#accountIFRS').value = account ? (account.ifrs_reference || '') : (options.prefill?.ifrs_reference || '');
    $('#accountActive').checked = account ? Boolean(account.is_active) : true;
    $('#templateSelect').value = '';

    accountModalInstance = accountModalInstance || new bootstrap.Modal(document.getElementById('accountModal'));
    accountModalInstance.show();
}

function promptStatusToggle(account) {
    if (!account) return;
    pendingStatusAccount = account;
    $('#statusModalBody').innerHTML = account.is_active
        ? `<strong>${account.code} · ${escapeHtml(account.name)}</strong> will be hidden from posting screens. Continue?`
        : `Reactivate <strong>${account.code} · ${escapeHtml(account.name)}</strong> so it becomes available again?`;
    statusModalInstance = statusModalInstance || new bootstrap.Modal(document.getElementById('statusModal'));
    statusModalInstance.show();
}

async function updateAccountStatus(account, shouldActivate) {
    try {
        const payload = {
            action: 'toggle_active',
            csrf_token: csrfToken,
            id: account.id,
            is_active: shouldActivate ? 1 : 0
        };

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to update status');
        }
        const index = accounts.findIndex(acc => acc.id === account.id);
        if (index !== -1) {
            accounts[index] = data.account;
        }
        renderAccountsTable();
        updateSummaryCards();
        showToast(`Account ${shouldActivate ? 'activated' : 'deactivated'} successfully.`);
    } catch (error) {
        console.error(error);
        showToast(error.message, 'danger');
    }
}

$('#confirmStatusChangeBtn').addEventListener('click', async () => {
    if (!pendingStatusAccount) {
        return;
    }
    const shouldActivate = !pendingStatusAccount.is_active;
    statusModalInstance.hide();
    await updateAccountStatus(pendingStatusAccount, shouldActivate);
    pendingStatusAccount = null;
});

$('#accountForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    $('#accountSubmitBtn').disabled = true;
    $('#accountSubmitBtn .default-text').style.display = 'none';
    $('#accountSubmitBtn .saving-text').style.display = '';

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload.is_active = formData.get('is_active') ? 1 : 0;

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to save account');
        }

        const savedAccount = data.account;
        const existingIndex = accounts.findIndex(acc => acc.id === savedAccount.id);
        if (existingIndex >= 0) {
            accounts[existingIndex] = savedAccount;
        } else {
            accounts.push(savedAccount);
        }

        renderAccountsTable();
        updateSummaryCards();
        showToast('Account saved successfully.');
        accountModalInstance.hide();
    } catch (error) {
        console.error(error);
        showToast(error.message, 'danger');
    } finally {
        $('#accountSubmitBtn').disabled = false;
        $('#accountSubmitBtn .default-text').style.display = '';
        $('#accountSubmitBtn .saving-text').style.display = 'none';
    }
});

$('#addAccountBtn').addEventListener('click', () => openAccountModal());
$('#guidedWizardBtn').addEventListener('click', () => {
    if (!revenueWizardModalInstance) {
        revenueWizardModalInstance = new bootstrap.Modal(document.getElementById('revenueWizardModal'));
    }
    openRevenueWizard();
});
$('#refreshTableBtn').addEventListener('click', () => fetchAccounts(false));
$('#accountSearch').addEventListener('input', () => renderAccountsTable());
$('#typeFilter').addEventListener('change', renderAccountsTable);
$('#classificationFilter').addEventListener('change', renderAccountsTable);

$('#wizardBackBtn').addEventListener('click', () => {
    if (wizardCurrentStep > 1) {
        setWizardStep(wizardCurrentStep - 1);
    }
});

$('#wizardNextBtn').addEventListener('click', () => {
    if (!validateWizardStep(wizardCurrentStep)) {
        return;
    }
    setWizardStep(wizardCurrentStep + 1);
});

$('#wizardFinishBtn').addEventListener('click', submitRevenueWizard);

$('#wizardNameInput').addEventListener('input', (event) => {
    wizardState.name = event.target.value;
    updateWizardSummary();
});

$('#wizardCodeInput').addEventListener('input', (event) => {
    event.target.value = event.target.value.toUpperCase();
    wizardState.code = event.target.value.trim();
    updateWizardSummary();
});

$('#wizardClassificationSelect').addEventListener('change', (event) => {
    wizardState.classification = event.target.value;
    if (wizardState.classification === 'OTHER_INCOME') {
        wizardState.type = 'OTHER_INCOME';
    } else if (wizardState.classification === 'CONTRA_REVENUE') {
        wizardState.type = 'CONTRA_REVENUE';
    } else {
        wizardState.type = 'REVENUE';
    }
    updateWizardSummary();
});

$('#wizardParentSelect').addEventListener('change', (event) => {
    wizardState.parentCode = event.target.value;
    updateWizardSummary();
});

$('#wizardDescriptionInput').addEventListener('input', (event) => {
    wizardState.description = event.target.value;
});

$$('#wizardTaxOptions input[type="radio"]').forEach((radio) => {
    radio.addEventListener('change', (event) => {
        wizardState.taxBehaviour = event.target.value;
        if (wizardState.taxBehaviour !== 'custom') {
            wizardState.customTaxRate = '';
            const customInput = $('#wizardCustomTaxInput');
            if (customInput) {
                customInput.value = '';
            }
        }
        syncWizardFormState();
        updateWizardSummary();
    });
});

$('#wizardCustomTaxInput').addEventListener('input', (event) => {
    wizardState.customTaxRate = event.target.value;
    updateWizardSummary();
});

$('#templateSelect').addEventListener('change', (event) => {
    const code = event.target.value;
    if (!code) return;
    const template = metadata.templates?.[code];
    if (!template) return;
    if (!$('#accountId').value) {
        $('#accountCode').value = code;
    }
    $('#accountName').value = template.name;
    $('#accountType').value = template.type;
    $('#accountClassification').value = template.classification;
    $('#accountStatement').value = template.statement_section;
    $('#accountParent').value = template.parent_code || '';
    $('#accountOrder').value = template.reporting_order ?? '';
    $('#accountIFRS').value = template.ifrs_reference || '';
});

function suggestNextRevenueCode() {
    const revenueAccounts = accounts
        .map(acc => acc.code)
        .filter(code => /^4\d{3}$/.test(code))
        .map(code => parseInt(code, 10))
        .sort((a, b) => a - b);
    if (!revenueAccounts.length) {
        return '4000';
    }
    const last = revenueAccounts[revenueAccounts.length - 1];
    return String(last + 10);
}

function escapeHtml(value) {
    if (value == null) {
        return '';
    }
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatLabel(value) {
    if (!value) return '—';
    return value.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, char => char.toUpperCase());
}

fetchAccounts();
</script>

<?php include 'includes/footer.php'; ?>
