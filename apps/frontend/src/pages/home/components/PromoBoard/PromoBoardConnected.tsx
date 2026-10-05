import PromoBoard from './PromoBoard';
import { DEMO_CARDS, DEMO_SLIDES } from './lib/demo-promo';
import { isPresent, toPromoItem, useHomeSliders } from '../../lib/home-sliders';

export default function PromoBoardConnected() {
  const { sliders } = useHomeSliders();
  const top = sliders?.top;

  const slides = (top?.main ?? []).map(toPromoItem);
  const rightTop = isPresent(top?.right_top) ? toPromoItem(top.right_top) : null;
  const rightBottom = isPresent(top?.right_bottom) ? toPromoItem(top.right_bottom) : null;

  // Заглушка — только пока бэкенд не отдал верхний блок вовсе
  if (!slides.length) {
    const [demoTop, demoBottom] = DEMO_CARDS;
    return <PromoBoard slides={DEMO_SLIDES} rightTop={demoTop} rightBottom={demoBottom} />;
  }

  return <PromoBoard slides={slides} rightTop={rightTop} rightBottom={rightBottom} />;
}
