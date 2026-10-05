import PromoBoard from './PromoBoard';
import { isPresent, toPromoItem, useHomeSliders } from '../../lib/home-sliders';

export default function PromoBoardConnected() {
  const { sliders } = useHomeSliders();
  const top = sliders?.top;

  const slides = (top?.main ?? []).map(toPromoItem);
  const rightTop = isPresent(top?.right_top) ? toPromoItem(top.right_top) : null;
  const rightBottom = isPresent(top?.right_bottom) ? toPromoItem(top.right_bottom) : null;

  return <PromoBoard slides={slides} rightTop={rightTop} rightBottom={rightBottom} />;
}
