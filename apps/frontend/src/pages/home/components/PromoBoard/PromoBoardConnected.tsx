import PromoBoard from './PromoBoard';
import { DEMO_CARDS, DEMO_SLIDES } from './lib/demo-promo';
import { isPresent, toPromoItem, useHomeSliders } from '../../lib/home-sliders';

const [DEMO_RIGHT_TOP, DEMO_RIGHT_BOTTOM] = DEMO_CARDS;

/**
 * Демо подставляется по частям: чего нет в админке — то и заменяется, остальное из API.
 * Иначе карточки из админки пропадали при пустом слайдере, а без карточки справа
 * оставалась пустая колонка. Когда админка заполнится — один запасной баннер. market-docs/41
 */
export default function PromoBoardConnected() {
  const { sliders } = useHomeSliders();
  const top = sliders?.top;

  const main = (top?.main ?? []).map(toPromoItem);
  const slides = main.length ? main : DEMO_SLIDES;
  const rightTop = isPresent(top?.right_top) ? toPromoItem(top.right_top) : DEMO_RIGHT_TOP;
  const rightBottom = isPresent(top?.right_bottom) ? toPromoItem(top.right_bottom) : DEMO_RIGHT_BOTTOM;

  return <PromoBoard slides={slides} rightTop={rightTop} rightBottom={rightBottom} />;
}
