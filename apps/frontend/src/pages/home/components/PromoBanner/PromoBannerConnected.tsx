import PromoBanner from './PromoBanner';
import { toPromoItem, useHomeSliders } from '../../lib/home-sliders';

export default function PromoBannerConnected() {
  const { sliders } = useHomeSliders();
  const slides = (sliders?.bottom ?? []).map(toPromoItem);

  return <PromoBanner slides={slides} />;
}
