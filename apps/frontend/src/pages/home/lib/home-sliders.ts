import useSWR from 'swr';
import { api } from '../../../lib/api';
import type { Slider } from '../../../lib/api';
import type { PromoItem } from '../components/PromoBoard/lib/promo-types';

/** Ключ общий: промо-блок и нижний баннер читают один ответ, запрос уходит один */
export const HOME_SLIDERS_KEY = '/api/sliders/home';

export function useHomeSliders() {
  const { data, isLoading } = useSWR(HOME_SLIDERS_KEY, () => api.sliders.home(), {
    revalidateOnFocus: false,
  });
  return { sliders: data?.data, isLoading };
}

/**
 * Пустая позиция приходит то как `null`, то как `{}` — в документации бэкенда
 * показан именно пустой объект. Считаем материалом только то, у чего есть id.
 */
export function isPresent(slider: Slider | null | undefined): slider is Slider {
  return Boolean(slider && slider.id);
}

/** Legacy records may still contain HTML from the old Filament RichEditor. */
export function sliderDescription(value: string | null | undefined): string | null {
  if (!value) return null;

  const withLineBreaks = value.replace(/<br\s*\/?\s*>|<\/p\s*>/gi, '\n');
  const withoutTags = withLineBreaks.replace(/<[^>]*>/g, '');

  return (
    withoutTags
      .replace(/&nbsp;/gi, ' ')
      .replace(/&amp;/gi, '&')
      .replace(/&lt;/gi, '<')
      .replace(/&gt;/gi, '>')
      .replace(/&quot;/gi, '"')
      .replace(/&#039;/gi, "'")
      .replace(/\n{3,}/g, '\n\n')
      .trim() || null
  );
}

export function toPromoItem(slider: Slider): PromoItem {
  return {
    id: slider.id,
    title: slider.title,
    description: sliderDescription(slider.description),
    badge: slider.badge_text,
    badgeTone: slider.badge_tone ?? undefined,
    image: slider.image_fullhd || slider.image_hd || slider.image,
    imageMobile:
      slider.image_mobile_hd || slider.image_mobile || slider.image_mobile_fullhd || null,
    to: slider.link,
    linkText: slider.button_text,
  };
}
