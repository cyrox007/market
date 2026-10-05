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

export function toPromoItem(slider: Slider): PromoItem {
  return {
    id: slider.id,
    title: slider.title,
    description: slider.description,
    badge: slider.badge_text,
    badgeTone: slider.badge_tone ?? undefined,
    image: slider.image_fullhd || slider.image_hd || slider.image,
    imageMobile:
      slider.image_mobile_hd || slider.image_mobile || slider.image_mobile_fullhd || null,
    to: slider.link,
    linkText: slider.button_text,
  };
}
