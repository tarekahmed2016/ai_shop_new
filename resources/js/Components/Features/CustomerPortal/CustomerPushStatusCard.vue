<script setup>
import { useCustomerPush } from '../../../Composables/useCustomerPush.js'
import PushNotificationStatusCard from '../../Common/PushNotificationStatusCard.vue'

const {
  status,
  errorMessage,
  processing,
  enableNotifications,
} = useCustomerPush()
</script>

<template>
    <PushNotificationStatusCard
        i18n-prefix="customerPush"
        :status="status"
        :error-message="errorMessage"
        :processing="processing"
        @enable="enableNotifications"
    />
</template>
