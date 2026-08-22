<?php

namespace App\Enums\MerchantPermissions;

enum PermissionKey: string
{
    case RequestsView = 'requests.view';
    case RequestsViewDetails = 'requests.view_details';
    case RequestsDismiss = 'requests.dismiss';

    case ActivitiesView = 'activities.view';
    case ActivitiesManage = 'activities.manage';

    case TeamView = 'team.view';
    case TeamAddStaff = 'team.add_staff';
    case TeamAddManager = 'team.add_manager';
    case TeamEditStaff = 'team.edit_staff';
    case TeamEditManager = 'team.edit_manager';
    case TeamRemoveStaff = 'team.remove_staff';
    case TeamRemoveManager = 'team.remove_manager';
    case TeamManagePermissions = 'team.manage_permissions';

    case MerchantProfileView = 'merchant_profile.view';
    case MerchantProfileUpdate = 'merchant_profile.update';

    case OffersView = 'offers.view';
    case OffersCreate = 'offers.create';
    case OffersUpdate = 'offers.update';
    case OffersWithdraw = 'offers.withdraw';

    public function groupKey(): string
    {
        return match ($this) {
            self::RequestsView, self::RequestsViewDetails, self::RequestsDismiss => 'requests',
            self::ActivitiesView, self::ActivitiesManage => 'activities',
            self::TeamView,
            self::TeamAddStaff,
            self::TeamAddManager,
            self::TeamEditStaff,
            self::TeamEditManager,
            self::TeamRemoveStaff,
            self::TeamRemoveManager,
            self::TeamManagePermissions => 'team',
            self::MerchantProfileView, self::MerchantProfileUpdate => 'merchant_profile',
            self::OffersView, self::OffersCreate, self::OffersUpdate, self::OffersWithdraw => 'offers',
        };
    }

    public function nameAr(): string
    {
        return match ($this) {
            self::RequestsView => 'عرض الطلبات',
            self::RequestsViewDetails => 'عرض تفاصيل الطلب',
            self::RequestsDismiss => 'إخفاء الطلبات',
            self::ActivitiesView => 'عرض الأنشطة',
            self::ActivitiesManage => 'إدارة الأنشطة',
            self::TeamView => 'عرض الفريق',
            self::TeamAddStaff => 'إضافة موظف',
            self::TeamAddManager => 'إضافة مدير',
            self::TeamEditStaff => 'تعديل موظف',
            self::TeamEditManager => 'تعديل مدير',
            self::TeamRemoveStaff => 'إزالة موظف',
            self::TeamRemoveManager => 'إزالة مدير',
            self::TeamManagePermissions => 'إدارة الصلاحيات',
            self::MerchantProfileView => 'عرض ملف التاجر',
            self::MerchantProfileUpdate => 'تعديل ملف التاجر',
            self::OffersView => 'عرض العروض',
            self::OffersCreate => 'إنشاء عرض',
            self::OffersUpdate => 'تعديل عرض',
            self::OffersWithdraw => 'سحب عرض',
        };
    }

    public function nameEn(): string
    {
        return match ($this) {
            self::RequestsView => 'View requests',
            self::RequestsViewDetails => 'View request details',
            self::RequestsDismiss => 'Dismiss requests',
            self::ActivitiesView => 'View activities',
            self::ActivitiesManage => 'Manage activities',
            self::TeamView => 'View team',
            self::TeamAddStaff => 'Add staff',
            self::TeamAddManager => 'Add manager',
            self::TeamEditStaff => 'Edit staff',
            self::TeamEditManager => 'Edit manager',
            self::TeamRemoveStaff => 'Remove staff',
            self::TeamRemoveManager => 'Remove manager',
            self::TeamManagePermissions => 'Manage permissions',
            self::MerchantProfileView => 'View merchant profile',
            self::MerchantProfileUpdate => 'Update merchant profile',
            self::OffersView => 'View offers',
            self::OffersCreate => 'Create offers',
            self::OffersUpdate => 'Update offers',
            self::OffersWithdraw => 'Withdraw offers',
        };
    }

    /**
     * Permissions that only an owner may grant (manager-level powers).
     *
     * @return list<self>
     */
    public static function managerLevelKeys(): array
    {
        return [
            self::TeamAddManager,
            self::TeamEditManager,
            self::TeamRemoveManager,
            self::TeamManagePermissions,
            self::MerchantProfileUpdate,
        ];
    }

    /**
     * @return list<self>
     */
    public static function allKeys(): array
    {
        return self::cases();
    }

    /**
     * @return list<self>
     */
    public static function ownerDefaults(): array
    {
        return self::allKeys();
    }

    /**
     * @return list<self>
     */
    public static function managerDefaults(): array
    {
        return [
            self::RequestsView,
            self::RequestsViewDetails,
            self::RequestsDismiss,
            self::ActivitiesView,
            self::ActivitiesManage,
            self::TeamView,
            self::TeamAddStaff,
            self::TeamEditStaff,
            self::TeamRemoveStaff,
            self::MerchantProfileView,
            self::OffersView,
            self::OffersCreate,
            self::OffersUpdate,
            self::OffersWithdraw,
        ];
    }

    /**
     * @return list<self>
     */
    public static function staffDefaults(): array
    {
        return [
            self::RequestsView,
            self::RequestsViewDetails,
            self::ActivitiesView,
            self::TeamView,
            self::MerchantProfileView,
            self::OffersView,
        ];
    }
}
