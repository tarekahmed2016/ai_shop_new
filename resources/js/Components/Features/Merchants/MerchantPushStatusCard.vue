<script setup>
import { useMerchantPush } from '../../../Composables/useMerchantPush.js'
import PushNotificationStatusCard from '../../Common/PushNotificationStatusCard.vue'

const {
  status,
  errorMessage,
  processing,
  enableNotifications,
} = useMerchantPush()
</script>

<template>
    <PushNotificationStatusCard
        i18n-prefix="merchantPush"
        :status="status"
        :error-message="errorMessage"
        :processing="processing"
        @enable="enableNotifications"
    />
</template>
