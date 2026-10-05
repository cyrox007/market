import PromoBanner from './PromoBanner';
import { DEMO_BANNERS } from './lib/demo-banner';
import { toPromoItem, useHomeSliders } from '../../lib/home-sliders';

export default function PromoBannerConnected() {
  const { sliders } = useHomeSliders();
  const slides = (sliders?.bottom ?? []).map(toPromoItem);

  return <PromoBanner slides={slides.length ? slides : DEMO_BANNERS} />;
}
