import useSWR from 'swr';
import PromoBoard from './PromoBoard';
import { api } from '../../../../lib/api';
import { DEMO_SLIDES, DEMO_CARDS } from './lib/demo-promo';
import type { PromoItem } from './lib/promo-types';
import type { Slider } from '../../../../lib/api';

const toPromoItem = (slider: Slider): PromoItem => ({
  id: slider.id,
  title: slider.title,
  description: slider.description,
  badge: slider.badge_text,
  badgeTone: 'red',
  image: slider.image_fullhd || slider.image_hd || slider.image,
  to: slider.link,
  linkText: slider.button_text,
});

export default function PromoBoardConnected() {
  const { data } = useSWR('/api/sliders', () => api.sliders.list(), {
    revalidateOnFocus: false,
  });

  const fromApi = (data?.data ?? []).map(toPromoItem);

  return (
    <PromoBoard
      slides={fromApi.length ? fromApi : DEMO_SLIDES}
      cards={fromApi.length > 1 ? fromApi.slice(1, 3) : DEMO_CARDS}
    />
  );
}
