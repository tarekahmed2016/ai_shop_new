import { computed, unref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  faFacebookF,
  faInstagram,
  faLinkedinIn,
  faXTwitter,
  faYoutube,
  faTiktok,
  faSnapchat,
  faWhatsapp,
} from '@fortawesome/free-brands-svg-icons'
import { faGlobe } from '@fortawesome/free-solid-svg-icons'

const SOCIAL_FIELDS = [
  { key: 'website', icon: faGlobe },
  { key: 'facebook', icon: faFacebookF },
  { key: 'instagram', icon: faInstagram },
  { key: 'x_twitter', icon: faXTwitter },
  { key: 'linkedin', icon: faLinkedinIn },
  { key: 'youtube', icon: faYoutube },
  { key: 'tiktok', icon: faTiktok },
  { key: 'snapchat', icon: faSnapchat },
  { key: 'whatsapp', icon: faWhatsapp },
]

const LABEL_KEYS = {
  website: 'public.home.contact.website',
  facebook: 'public.home.contact.facebook',
  instagram: 'public.home.contact.instagram',
  linkedin: 'public.home.contact.linkedin',
  x_twitter: 'public.home.contact.xTwitter',
  youtube: 'public.home.contact.youtube',
  tiktok: 'public.home.contact.tiktok',
  snapchat: 'public.home.contact.snapchat',
  whatsapp: 'public.home.contact.whatsapp',
}

export function useSocialLinks(companyInfoSource) {
  const { t } = useI18n()

  const socialLinks = computed(() => {
    const source = unref(companyInfoSource)
    const companyInfo = (typeof source === 'function' ? source() : source) || {}

    return SOCIAL_FIELDS
      .map(({ key, icon }) => ({
        key,
        url: companyInfo[key] || '',
        label: t(LABEL_KEYS[key]),
        icon,
      }))
      .filter((link) => Boolean(link.url))
  })

  const socialLinksWithoutWebsite = computed(() =>
    socialLinks.value.filter((link) => link.key !== 'website')
  )

  return {
    socialLinks,
    socialLinksWithoutWebsite,
  }
}
