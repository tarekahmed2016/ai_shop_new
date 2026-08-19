import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const defaultFormValues = (companyInfo) => ({
  name_ar: companyInfo.name_ar || '',
  name_en: companyInfo.name_en || '',
  phone: companyInfo.phone || '',
  email: companyInfo.email || '',
  hero_title_ar: companyInfo.hero_title_ar || '',
  hero_title_en: companyInfo.hero_title_en || '',
  hero_description_ar: companyInfo.hero_description_ar || '',
  hero_description_en: companyInfo.hero_description_en || '',
  about_ar: companyInfo.about_ar || '',
  about_en: companyInfo.about_en || '',
  vision_ar: companyInfo.vision_ar || '',
  vision_en: companyInfo.vision_en || '',
  mission_ar: companyInfo.mission_ar || '',
  mission_en: companyInfo.mission_en || '',
  address_ar: companyInfo.address_ar || '',
  address_en: companyInfo.address_en || '',
  website: companyInfo.website || '',
  facebook: companyInfo.facebook || '',
  instagram: companyInfo.instagram || '',
  linkedin: companyInfo.linkedin || '',
  x_twitter: companyInfo.x_twitter || '',
  youtube: companyInfo.youtube || '',
  tiktok: companyInfo.tiktok || '',
  snapchat: companyInfo.snapchat || '',
  whatsapp: companyInfo.whatsapp || '',
  logo: null,
})

export function useCompanyInfo(companyInfo) {
  const form = useForm(defaultFormValues(companyInfo))

  const logoInput = ref(null)
  const logoFileName = ref(null)
  const logoPreview = ref(companyInfo.attachment?.asset_path || null)

  const handleLogoChange = (event) => {
    const file = event.target.files[0] || null
    form.logo = file
    logoFileName.value = file?.name || null
    logoPreview.value = file ? URL.createObjectURL(file) : (companyInfo.attachment?.asset_path || null)
  }

  // Laravel can't parse multipart bodies on native PUT requests, so spoof the method via POST
  const updateCompanyInfo = (options = {}) =>
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('company-info.update'), {
      preserveScroll: true,
      onSuccess: () => {
        form.logo = null
        logoFileName.value = null
        if (logoInput.value) logoInput.value.value = ''
      },
      ...options,
    })

  return {
    form,
    logoInput,
    logoFileName,
    logoPreview,
    handleLogoChange,
    updateCompanyInfo,
  }
}
