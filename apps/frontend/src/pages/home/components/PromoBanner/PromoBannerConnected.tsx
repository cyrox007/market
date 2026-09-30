import useSWR from 'swr';
import PromoBanner from './PromoBanner';
import { api } from '../../../../lib/api';
import { DEMO_BANNERS } from './lib/demo-banner';
import type { PromoItem } from '../PromoBoard/lib/promo-types';
import type { Slider } from '../../../../lib/api';

const toPromoItem = (slider: Slider): PromoItem => ({
  id: slider.id,
  title: slider.title,
  description: slider.description,
  badge: slider.badge_text,
  image: slider.image_fullhd || slider.image_hd || slider.image,
  to: slider.link,
  linkText: slider.button_text,
});

export default function PromoBannerConnected() {
  const { data } = useSWR('/api/sliders', () => api.sliders.list(), { revalidateOnFocus: false });

  const fromApi = (data?.data ?? []).map(toPromoItem);

  return <PromoBanner slides={fromApi.length ? fromApi : DEMO_BANNERS} />;
}
