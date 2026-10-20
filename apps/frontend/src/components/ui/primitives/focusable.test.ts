import { describe, expect, it } from 'vitest';
import { focusableIn } from './focusable';

function render(html: string) {
  const root = document.createElement('div');
  root.innerHTML = html;
  document.body.append(root);
  return root;
}

const names = (els: HTMLElement[]) => els.map((el) => el.dataset.name);

describe('куда встаёт Tab', () => {
  it('ссылки, кнопки, поля и tabindex — в порядке документа', () => {
    const root = render(`
      <a href="/x" data-name="link"></a>
      <button data-name="button"></button>
      <input data-name="input" />
      <div tabindex="0" data-name="tabindex"></div>
      <textarea data-name="textarea"></textarea>
    `);
    expect(names(focusableIn(root))).toEqual(['link', 'button', 'input', 'tabindex', 'textarea']);
  });

  it('без выключенных, скрытых полей, tabindex=-1 и ссылок без адреса', () => {
    const root = render(`
      <a data-name="no-href"></a>
      <button disabled data-name="disabled"></button>
      <input type="hidden" data-name="hidden-input" />
      <div tabindex="-1" data-name="minus-one"></div>
      <div hidden><button data-name="in-hidden"></button></div>
      <button data-name="ok"></button>
    `);
    expect(names(focusableIn(root))).toEqual(['ok']);
  });
});
