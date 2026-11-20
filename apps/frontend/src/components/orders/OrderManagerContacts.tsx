import type { OrderManagerContact } from '../../lib/api';

export default function OrderManagerContacts({ contact }: { contact?: OrderManagerContact }) {
  if (!contact?.primary_action) return null;
  const primary = contact.primary_action;
  return (
    <div className="mb-6 flex flex-wrap items-center gap-3" aria-label="Контакты менеджера заказа">
      <a href={primary.url} className="rounded-lg border border-gray-300 px-4 py-2 font-medium hover:bg-gray-50">
        Связаться с менеджером
      </a>
      {contact.actions.filter((action) => action.type !== primary.type).map((action) => (
        <a key={action.type} href={action.url} className="text-sm underline">{action.label}</a>
      ))}
      {contact.hours && <span className="text-sm text-gray-600">{contact.hours}</span>}
    </div>
  );
}
