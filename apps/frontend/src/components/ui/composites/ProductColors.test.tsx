import { fireEvent, render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import SelectedColors from './SelectedColors';

it('shows named elements with material images and keeps their names when an image fails', () => {
  render(
    <SelectedColors
      colors={[
        {
          element: { id: 1, name: 'Фасад', slug: 'fasad' },
          solution: { id: 1, name: 'Дуб Юкон', slug: 'oak', icon_url: '/oak.png', base_colors: [] },
        },
        {
          element: null,
          solution: {
            id: 2,
            name: 'Белый / графит',
            slug: 'white-gray',
            icon_url: null,
            base_colors: [
              { id: 1, name: 'Белый', slug: 'white', hex: '#ffffff' },
              { id: 2, name: 'Графит', slug: 'gray', hex: '#333333' },
            ],
          },
        },
      ]}
    />,
  );
  expect(screen.getByText('Фасад: Дуб Юкон')).toBeInTheDocument();
  expect(screen.getByText('Белый / графит')).toBeInTheDocument();
  expect(screen.getByText('Белый / графит').previousElementSibling).toHaveStyle({
    background: 'linear-gradient(90deg, #ffffff 0% 50%, #333333 50% 100%)',
  });
  fireEvent.error(document.querySelector('img')!);
  expect(screen.getByText('Фасад: Дуб Юкон')).toBeInTheDocument();
  expect(document.querySelector('img')).not.toBeInTheDocument();
});
