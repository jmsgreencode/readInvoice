import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './app.css';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }
  static getDerivedStateFromError(error) {
    return { error };
  }
  render() {
    if (this.state.error) {
      return React.createElement('pre', {
        style: { color: 'red', padding: '2rem', whiteSpace: 'pre-wrap' }
      }, 'React Error:\n' + this.state.error.message + '\n\n' + this.state.error.stack);
    }
    return this.props.children;
  }
}

const root = createRoot(document.getElementById('root'));
root.render(
  <ErrorBoundary>
    <App />
  </ErrorBoundary>
);
