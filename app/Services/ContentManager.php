<?php
/**
 * Simple Content Management System
 * Edit website content without touching code
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

namespace App\Services;

class ContentManager
{
    private static ?ContentManager $instance = null;
    private \Database $db;
    private array $cache = [];

    private function __construct()
    {
        $this->db = \Database::getInstance();
        $this->ensureTableExists();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get content by key
     */
    public function get(string $key, string $default = ''): string
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            $stmt = $this->db->query(
                "SELECT content FROM site_content WHERE content_key = ? AND is_active = 1",
                [$key]
            );
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $content = $result['content'] ?? $default;
        } catch (\Throwable $e) {
            // Table might not exist yet
            $content = $default;
        }
        
        $this->cache[$key] = $content;
        return $content;
    }

    /**
     * Get content as array (for JSON stored content)
     */
    public function getArray(string $key, array $default = []): array
    {
        $content = $this->get($key, '');
        if (empty($content)) {
            return $default;
        }
        
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Set content
     */
    public function set(string $key, string $content, string $type = 'text', string $page = 'home'): bool
    {
        $stmt = $this->db->query(
            "SELECT id FROM site_content WHERE content_key = ?",
            [$key]
        );
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            $this->db->query(
                "UPDATE site_content SET content = ?, updated_at = NOW() WHERE content_key = ?",
                [$content, $key]
            );
        } else {
            $this->db->query(
                "INSERT INTO site_content (content_key, content, content_type, page, is_active, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, 1, NOW(), NOW())",
                [$key, $content, $type, $page]
            );
        }

        $this->cache[$key] = $content;
        return true;
    }

    /**
     * Get all content for a page
     */
    public function getPageContent(string $page): array
    {
        $stmt = $this->db->query(
            "SELECT content_key, content, content_type FROM site_content WHERE page = ? AND is_active = 1",
            [$page]
        );
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $content = [];
        foreach ($results as $row) {
            $content[$row['content_key']] = [
                'content' => $row['content'],
                'type' => $row['content_type'],
            ];
        }
        return $content;
    }

    /**
     * Get all editable content
     */
    public function getAllContent(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM site_content ORDER BY page, content_key"
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Delete content
     */
    public function delete(string $key): bool
    {
        $this->db->query("DELETE FROM site_content WHERE content_key = ?", [$key]);
        unset($this->cache[$key]);
        return true;
    }

    /**
     * Seed default content
     */
    public function seedDefaults(): void
    {
        $defaults = [
            // Home page
            ['home_hero_title', 'Complete Business Management System', 'text', 'home'],
            ['home_hero_subtitle', 'Point of Sale, Restaurant Operations, Inventory, Deliveries, Housekeeping, Maintenance, and Accounting — all in one unified platform.', 'textarea', 'home'],
            ['home_cta_button', 'Sign In to Dashboard', 'text', 'home'],
            
            // Company info
            ['company_name', 'WAPOS', 'text', 'global'],
            ['company_tagline', 'by Waesta Enterprises', 'text', 'global'],
            ['company_full_name', 'Waesta Enterprises U Ltd', 'text', 'global'],
            ['company_email', 'info@waesta.com', 'text', 'global'],
            ['company_phone', '+254 700 000 000', 'text', 'global'],
            ['company_address', 'Nairobi, Kenya', 'textarea', 'global'],
            ['company_website', 'https://waesta.com', 'text', 'global'],
            
            // About page
            ['about_title', 'About WAPOS', 'text', 'about'],
            ['about_content', 'WAPOS is a comprehensive point of sale and business management system designed for retail, restaurant, and hospitality businesses.', 'richtext', 'about'],
            
            // Contact page
            ['contact_title', 'Contact Us', 'text', 'contact'],
            ['contact_intro', 'Get in touch with our team for support or inquiries.', 'textarea', 'contact'],
            
            // Footer
            ['footer_copyright', '© ' . date('Y') . ' Waesta Enterprises U Ltd. All rights reserved.', 'text', 'global'],
            
            // SEO
            ['seo_title', 'WAPOS - Unified Point of Sale System', 'text', 'seo'],
            ['seo_description', 'WAPOS is a comprehensive point of sale system for retail, restaurant, and hospitality businesses.', 'textarea', 'seo'],
            ['seo_keywords', 'POS system, point of sale, retail POS, restaurant POS', 'textarea', 'seo'],
        ];

        foreach ($defaults as [$key, $content, $type, $page]) {
            $stmt = $this->db->query(
                "SELECT id FROM site_content WHERE content_key = ?",
                [$key]
            );
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$existing) {
                $this->set($key, $content, $type, $page);
            }
        }
    }

    private function ensureTableExists(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS site_content (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_key VARCHAR(100) NOT NULL UNIQUE,
                content TEXT,
                content_type ENUM('text', 'textarea', 'richtext', 'html', 'json', 'image') DEFAULT 'text',
                page VARCHAR(50) DEFAULT 'home',
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_page (page),
                INDEX idx_key (content_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

/**
 * Helper function for templates
 */
function content(string $key, string $default = ''): string
{
    return \App\Services\ContentManager::getInstance()->get($key, $default);
}

function contentArray(string $key, array $default = []): array
{
    return \App\Services\ContentManager::getInstance()->getArray($key, $default);
}
