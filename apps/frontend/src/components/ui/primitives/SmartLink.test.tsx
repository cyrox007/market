import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import SmartLink from './SmartLink';

const renderLink = (to: string) =>
  render(
    <MemoryRouter>
      <SmartLink to={to}>Подробнее</SmartLink>
    </MemoryRouter>,
  ).container.querySelector('a')!;

describe('SmartLink', () => {
  it('keeps internal paths inside the site', () => {
    const link = renderLink('/catalog/gostinaia/gotovye-stenki');
    expect(link).toHaveAttribute('href', '/catalog/gostinaia/gotovye-stenki');
    expect(link).not.toHaveAttribute('target');
  });

  it('opens external addresses as they are, in a new tab', () => {
    const link = renderLink('https://example.com/promo');
    expect(link).toHaveAttribute('href', 'https://example.com/promo');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('leaves phone links in the same tab', () => {
    const link = renderLink('tel:88002228586');
    expect(link).toHaveAttribute('href', 'tel:88002228586');
    expect(screen.getByText('Подробнее')).toBeInTheDocument();
    expect(link).not.toHaveAttribute('target');
  });
});
