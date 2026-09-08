import { organizationRequisites } from '../../data/organizationRequisites';

export default function FooterRequisites() {
  const r = organizationRequisites;
  return (
    <div
      aria-label="Реквизиты организации"
      className="mx-auto text-center md:text-left text-xs text-gray-500 leading-relaxed space-y-2"
    >
      <p>
        ИНН {r.inn}, КПП {r.kpp}, ОГРН {r.ogrn}, ОКПО {r.okpo}
      </p>
      <p>Юридический адрес: {r.legalAddress}</p>
      <p>Генеральный директор: {r.director}</p>
    </div>
  );
}
