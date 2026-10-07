import PromoBanner from './PromoBanner';
import { toPromoItem, useHomeSliders } from '../../lib/home-sliders';

/** Слайды — только из админки (`bottom`); нет слайдов — нет баннера. market-docs/26 */
export default function PromoBannerConnected() {
  const { sliders } = useHomeSliders();
  const slides = (sliders?.bottom ?? []).map(toPromoItem);

  return <PromoBanner slides={slides} />;
}
