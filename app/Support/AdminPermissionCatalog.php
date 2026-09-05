<?php

namespace App\Support;

final class AdminPermissionCatalog
{
    public const MANAGE_ADMIN_ROLE = 'admin-users.manage-admin-role';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            self::MANAGE_ADMIN_ROLE,
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'services.view',
            'services.create',
            'services.update',
            'services.delete',
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'team-members.view',
            'team-members.create',
            'team-members.update',
            'team-members.delete',
            'clients-partners.view',
            'clients-partners.create',
            'clients-partners.update',
            'clients-partners.delete',
            'certificates-awards.view',
            'certificates-awards.create',
            'certificates-awards.update',
            'certificates-awards.delete',
            'contact-messages.view',
            'contact-messages.update',
            'contact-messages.delete',
            'settings.update',
            'pages.view',
            'pages.create',
            'pages.update',
            'pages.delete',
            'hero-slides.view',
            'hero-slides.create',
            'hero-slides.update',
            'hero-slides.delete',
            'homepage-promos.view',
            'homepage-promos.create',
            'homepage-promos.update',
            'homepage-promos.delete',
            'newsletter-subscribers.view',
            'newsletter-subscribers.update',
            'newsletter-subscribers.delete',
            'marketers.view',
            'marketers.create',
            'marketers.update',
            'marketers.approve',
            'marketers.reject',
            'marketers.activate',
            'marketers.payout',
            'marketer-commissions.manage-settings',
            'merchant-credits.view',
            'merchant-credits.add',
            'merchant-credits.deduct',
            'merchant-credits.manage-settings',
            'categories.view',
            'categories.create',
            'categories.update',
            'merchants.view',
            'merchants.create',
            'merchants.update',
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.manage-limits',
            'customer-requests.view',
            'customer-requests.create',
            'customer-requests.update',
            'matching.view',
            'matching.recalculate',
            'payments.view',
        ];
    }

    /**
     * Permissions that may upload inline rich-text images.
     *
     * @return list<string>
     */
    public static function richTextUploadPermissions(): array
    {
        return [
            'pages.create',
            'pages.update',
            'services.create',
            'services.update',
            'projects.create',
            'projects.update',
            'team-members.create',
            'team-members.update',
            'certificates-awards.create',
            'certificates-awards.update',
            'hero-slides.create',
            'hero-slides.update',
            'homepage-promos.create',
            'homepage-promos.update',
            'settings.update',
        ];
    }
}
