<?php

use App\Services\PromotionService;

if (!function_exists('loadActivePromotions')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function loadActivePromotions(PDO $pdo): array
    {
        try {
            $service = new PromotionService($pdo);
            $service->ensureSchema();
            return $service->getActivePromotions();
        } catch (Throwable $e) {
            error_log('Promotion spotlight failed: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('renderPromotionSpotlight')) {
    /**
     * @param array<int, array<string, mixed>> $promotions
     * @param array<string, mixed> $options
     */
    function renderPromotionSpotlight(array $promotions, array $options = []): void
    {
        static $promoStylesInjected = false;

        $title = $options['title'] ?? 'Promotion Spotlight';
        $description = $options['description'] ?? 'Limited-time offers automatically apply when their criteria are met.';
        $icon = $options['icon'] ?? 'bi-stars';
        $manageUrl = $options['manage_url'] ?? '/wapos/manage-promotions.php';
        $showManageLink = (bool)($options['show_manage_link'] ?? false);
        $emptyState = $options['empty_state'] ?? 'No scheduled promotions right now.';
        $ctaLabel = $options['cta_label'] ?? 'Manage Promotions';
        $maxItems = max(1, (int)($options['max_items'] ?? 4));
        $context = $options['context'] ?? 'global';

        $totalCount = count($promotions);
        $visiblePromotions = array_slice($promotions, 0, $maxItems);

        if (!$promoStylesInjected) {
            $promoStylesInjected = true;
            echo '<style>' . PHP_EOL . trim(<<<'CSS'
.promo-spotlight {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
    padding: var(--spacing-lg);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}
.promo-spotlight-head {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: var(--spacing-sm);
}
.promo-spotlight-count {
    font-weight: 600;
    font-size: 0.95rem;
}
.promo-spotlight-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}
.promo-spotlight-item {
    border: 1px solid var(--color-border-subtle, rgba(0,0,0,0.08));
    border-radius: var(--radius-md);
    padding: var(--spacing-md);
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.promo-spotlight-item h6 {
    margin: 0;
    font-size: 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: baseline;
}
.promo-spotlight-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.85rem;
    color: var(--color-text-muted);
}
.promo-spotlight-value {
    font-weight: 600;
}
.promo-spotlight-footer {
    display: flex;
    justify-content: flex-end;
}
CSS
            ) . PHP_EOL . '</style>' . PHP_EOL;
        }

        echo '<section class="app-card promo-spotlight" data-context="' . htmlspecialchars((string)$context, ENT_QUOTES, 'UTF-8') . '" aria-live="polite">';
        echo '<div class="promo-spotlight-head">';
        echo '<div>';
        echo '<p class="text-muted small mb-1">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<h5 class="mb-0"><i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' me-2"></i>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h5>';
        echo '</div>';
        echo '<span class="promo-spotlight-count badge bg-light text-dark">' . $totalCount . ' live</span>';
        echo '</div>';

        if ($totalCount === 0) {
            echo '<div class="text-muted text-center py-3">';
            echo '<i class="bi bi-magic fs-3 d-block mb-2"></i>';
            echo '<span>' . htmlspecialchars($emptyState, ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</div>';
        } else {
            echo '<ul class="promo-spotlight-list">';
            foreach ($visiblePromotions as $promotion) {
                $productName = $promotion['product_name'] ?? null;
                $valueSummary = describePromotionValue($promotion);
                $scheduleSummary = describePromotionSchedule($promotion);
                $typeLabel = describePromotionType($promotion);

                echo '<li class="promo-spotlight-item">';
                echo '<div class="d-flex justify-content-between align-items-start gap-2">';
                echo '<div>';
                echo '<h6>' . htmlspecialchars($promotion['name'] ?? 'Promotion', ENT_QUOTES, 'UTF-8');
                if ($productName) {
                    echo '<small class="text-muted"> · ' . htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') . '</small>';
                }
                echo '</h6>';
                if (!empty($promotion['description'])) {
                    echo '<p class="text-muted small mb-0">' . htmlspecialchars($promotion['description'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                echo '</div>';
                echo '<span class="badge bg-primary-subtle text-primary fw-semibold text-uppercase">' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</span>';
                echo '</div>';
                if ($valueSummary) {
                    echo '<div class="promo-spotlight-value">' . htmlspecialchars($valueSummary, ENT_QUOTES, 'UTF-8') . '</div>';
                }
                if ($scheduleSummary) {
                    echo '<div class="promo-spotlight-meta">' . htmlspecialchars($scheduleSummary, ENT_QUOTES, 'UTF-8') . '</div>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        if ($showManageLink) {
            echo '<div class="promo-spotlight-footer">';
            echo '<a class="btn btn-outline-primary btn-sm" href="' . htmlspecialchars($manageUrl, ENT_QUOTES, 'UTF-8') . '">';
            echo '<i class="bi bi-sliders me-2"></i>' . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');
            echo '</a>';
            echo '</div>';
        }

        echo '</section>';
    }
}

if (!function_exists('describePromotionValue')) {
    /**
     * @param array<string, mixed> $promotion
     */
    function describePromotionValue(array $promotion): string
    {
        $minQuantity = (int)($promotion['min_quantity'] ?? 1);
        $type = $promotion['promotion_type'] ?? 'bundle_price';

        if ($type === 'bundle_price' && isset($promotion['bundle_price'])) {
            return sprintf('%s for %d item%s',
                formatMoney((float)$promotion['bundle_price']),
                max(1, $minQuantity),
                $minQuantity > 1 ? 's' : ''
            );
        }

        if ($type === 'percent' && isset($promotion['discount_value'])) {
            return rtrim(rtrim(number_format((float)$promotion['discount_value'], 2, '.', ''), '0'), '.') . "% off · Min qty " . max(1, $minQuantity);
        }

        if ($type === 'fixed' && isset($promotion['discount_value'])) {
            return sprintf('%s off each · Min qty %d',
                formatMoney((float)$promotion['discount_value']),
                max(1, $minQuantity)
            );
        }

        return '';
    }
}

if (!function_exists('describePromotionSchedule')) {
    /**
     * @param array<string, mixed> $promotion
     */
    function describePromotionSchedule(array $promotion): string
    {
        $parts = [];

        $days = $promotion['days_of_week'] ?? [];
        if (is_array($days) && !empty($days)) {
            $map = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            $labels = [];
            foreach ($days as $day) {
                $index = (int)$day;
                $labels[] = $map[$index] ?? (string)$index;
            }
            $parts[] = implode(', ', $labels);
        } else {
            $parts[] = 'All days';
        }

        $startTime = $promotion['start_time'] ?? null;
        $endTime = $promotion['end_time'] ?? null;
        if ($startTime || $endTime) {
            $start = $startTime ? substr($startTime, 0, 5) : '00:00';
            $end = $endTime ? substr($endTime, 0, 5) : '23:59';
            $parts[] = $start . ' – ' . $end;
        } else {
            $parts[] = 'All day';
        }

        $startDate = $promotion['start_date'] ?? null;
        $endDate = $promotion['end_date'] ?? null;
        if ($startDate || $endDate) {
            $startLabel = $startDate ? date('M j', strtotime($startDate)) : 'Any';
            $endLabel = $endDate ? date('M j', strtotime($endDate)) : 'Open';
            $parts[] = $startLabel . ' → ' . $endLabel;
        } else {
            $parts[] = 'No end date';
        }

        return implode(' · ', array_filter($parts));
    }
}

if (!function_exists('describePromotionType')) {
    /**
     * @param array<string, mixed> $promotion
     */
    function describePromotionType(array $promotion): string
    {
        return match ($promotion['promotion_type'] ?? 'bundle_price') {
            'percent' => 'Percent',
            'fixed' => 'Fixed',
            default => 'Bundle',
        };
    }
}
