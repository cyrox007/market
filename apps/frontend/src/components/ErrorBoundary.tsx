import { Component, type ErrorInfo, type ReactNode } from 'react';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  isChunkError: boolean;
}

function isChunkLoadError(error: unknown): boolean {
  const message = error instanceof Error ? error.message : String(error);
  return (
    message.includes('Failed to fetch dynamically imported module') ||
    message.includes('Loading chunk') ||
    message.includes('Importing a module script failed')
  );
}

export default class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, isChunkError: false };

  static getDerivedStateFromError(error: unknown): State {
    return { hasError: true, isChunkError: isChunkLoadError(error) };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('App error:', error, info.componentStack);
    if (isChunkLoadError(error) && !sessionStorage.getItem('chunk_reload')) {
      sessionStorage.setItem('chunk_reload', '1');
      window.location.reload();
    }
  }

  componentDidUpdate(_: Props, prevState: State) {
    if (prevState.hasError && !this.state.hasError) {
      sessionStorage.removeItem('chunk_reload');
    }
  }

  handleRetry = () => {
    sessionStorage.removeItem('chunk_reload');
    this.setState({ hasError: false, isChunkError: false });
    window.location.reload();
  };

  render() {
    if (!this.state.hasError) {
      return this.props.children;
    }

    return (
      <div className="min-h-screen bg-white flex items-center justify-center px-4">
        <div className="max-w-md text-center">
          <h1 className="text-xl font-bold mb-2">Не удалось загрузить страницу</h1>
          <p className="text-gray-600 mb-6">
            {this.state.isChunkError
              ? 'Обновите страницу — возможно, вышло обновление сайта.'
              : 'Произошла ошибка. Попробуйте обновить страницу.'}
          </p>
          <button
            type="button"
            onClick={this.handleRetry}
            className="bg-red-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-red-700"
          >
            Обновить
          </button>
        </div>
      </div>
    );
  }
}
