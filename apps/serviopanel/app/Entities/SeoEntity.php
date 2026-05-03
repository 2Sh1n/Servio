<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class SeoEntity extends Entity
{
    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at'];
    protected $casts = [];

    /**
     * Get formatted SEO data for API responses
     * 
     * @return array
     */
    public function getFormattedData(): array
    {
        $data = [
            'id' => $this->id ?? null,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'keywords' => $this->keywords ?? '',
            'schema_markup' => $this->schema_markup ?? '',
            'image' => $this->getFormattedImage(),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];

        // Add reference ID based on context
        if (isset($this->service_id)) {
            $data['service_id'] = $this->service_id;
        }
        if (isset($this->category_id)) {
            $data['category_id'] = $this->category_id;
        }
        if (isset($this->partner_id)) {
            $data['partner_id'] = $this->partner_id;
        }
        if (isset($this->blog_id)) {
            $data['blog_id'] = $this->blog_id;
        }
        if (isset($this->page)) {
            $data['page'] = $this->page;
        }

        return $data;
    }

    /**
     * Get formatted image URL
     * 
     * @return string
     */
    public function getFormattedImage(): string
    {
        if (empty($this->image)) {
            return '';
        }

        // Determine SEO type based on available fields
        $seoType = $this->getSeoType();

        // Use the same logic as in the model
        $disk = function_exists('fetch_current_file_manager') ? fetch_current_file_manager() : 'local_server';

        if ($disk == 'local_server') {
            // Use different folder paths based on SEO type
            $folderPath = $this->getSeoImageFolder($seoType);
            return base_url($folderPath . $this->image);
        } else {
            // For cloud storage, use appropriate folder name
            $cloudFolder = $this->getCloudSeoFolder($seoType);
            return function_exists('fetch_cloud_front_url')
                ? fetch_cloud_front_url($cloudFolder, $this->image)
                : base_url('public/uploads/seo_settings/general_seo_settings/' . $this->image);
        }
    }

    /**
     * Determine SEO type based on available fields
     * 
     * @return string
     */
    private function getSeoType(): string
    {
        if (isset($this->service_id)) {
            return 'services';
        }
        if (isset($this->category_id)) {
            return 'categories';
        }
        if (isset($this->partner_id)) {
            return 'providers';
        }
        if (isset($this->blog_id)) {
            return 'blogs';
        }
        if (isset($this->page) && strpos($this->page, 'custom_page_') === 0) {
            return 'custom_pages';
        }
        return 'general';
    }

    /**
     * Get local server image folder path based on SEO type
     * 
     * @param string $seoType
     * @return string
     */
    private function getSeoImageFolder(string $seoType): string
    {
        switch ($seoType) {
            case 'services':
                return 'public/uploads/seo_settings/service_seo_settings/';
            case 'categories':
                return 'public/uploads/seo_settings/category_seo_settings/';
            case 'providers':
                return 'public/uploads/seo_settings/provider_seo_settings/';
            case 'blogs':
                return 'public/uploads/seo_settings/blog_seo_settings/';
            case 'custom_pages':
                return 'public/uploads/seo_settings/custom_page_seo_settings/';
            default:
                return 'public/uploads/seo_settings/general_seo_settings/';
        }
    }

    /**
     * Get cloud storage folder name based on SEO type
     * 
     * @param string $seoType
     * @return string
     */
    private function getCloudSeoFolder(string $seoType): string
    {
        switch ($seoType) {
            case 'services':
                return 'services_seo_settings';
            case 'categories':
                return 'categories_seo_settings';
            case 'providers':
                return 'providers_seo_settings';
            case 'blogs':
                return 'blogs_seo_settings';
            case 'custom_pages':
                return 'custom_page_seo_settings';
            default:
                return 'seo_settings';
        }
    }

    /**
     * Get compact SEO data for API responses (minimal fields)
     * 
     * @return array
     */
    public function getCompactData(): array
    {
        return [
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'keywords' => $this->keywords ?? '',
            'image' => $this->getFormattedImage(),
        ];
    }

    /**
     * Get SEO data for meta tags
     * 
     * @return array
     */
    public function getMetaData(): array
    {
        return [
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'keywords' => $this->keywords ?? '',
            'image' => $this->getFormattedImage(),
            'schema_markup' => $this->schema_markup ?? '',
        ];
    }

    /**
     * Check if SEO data exists (has any meaningful content)
     * 
     * @return bool
     */
    public function hasContent(): bool
    {
        return !empty($this->title) ||
            !empty($this->description) ||
            !empty($this->keywords) ||
            !empty($this->image) ||
            !empty($this->schema_markup);
    }
}
