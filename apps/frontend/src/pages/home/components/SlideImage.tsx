interface SlideImageProps {
  image?: string | null;
  imageMobile?: string | null;
  lazy?: boolean;
}

/** Фон слайда. Ниже 550 — своё кадрирование, если бэкенд его отдал; иначе браузер берёт обычное */
export default function SlideImage({ image, imageMobile, lazy = false }: SlideImageProps) {
  if (!image) return null;

  return (
    <picture>
      {imageMobile ? <source media="(max-width: 549px)" srcSet={imageMobile} /> : null}
      <img
        src={image}
        alt=""
        loading={lazy ? 'lazy' : undefined}
        className="absolute inset-0 -z-10 size-full object-cover"
      />
    </picture>
  );
}
