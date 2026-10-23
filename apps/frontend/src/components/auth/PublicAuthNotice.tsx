import { Link } from 'react-router-dom';

export default function PublicAuthNotice({ title, retry }: { title: string; retry?: () => unknown }) {
  return (
    <main className="mx-auto max-w-md px-4 py-12">
      <p role="status" className="mb-6 text-lg">{title}</p>
      {retry && <button type="button" onClick={retry} className="mr-4 underline">Повторить</button>}
      <Link to="/" className="underline">На главную</Link>
    </main>
  );
}
