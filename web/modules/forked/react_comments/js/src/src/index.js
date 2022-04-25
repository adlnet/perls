import React from 'react';
import ReactDOM from 'react-dom';
import App from './App';
// import registerServiceWorker from './registerServiceWorker';
import './index.css';
import api from './utils/api';

const appContainer = document.getElementById(api.getAppContainerId());

if (appContainer) {
  ReactDOM.render(<App />, appContainer);
}

window.renderComments = () => ReactDOM.render(<App />, document.getElementById('comments-app-container'));
// registerServiceWorker();
