import axios from 'axios';

const debug = (process.env.NODE_ENV === 'development');

export default class Api {
    static getApiBaseUrl() {
        return debug ? window.commentsApiBaseUrl : window.location.origin;
    }

    static createCsrfPromise() {
        return axios.get(`${this.getApiBaseUrl()}/session/token`, {withCredentials: true})
            .then((response) => {
                return response.data;
            }).catch((err) => {
                throw new Error(this.formatErrorMessage(err));
            });
    }

    static getAppContainerId() {
        return 'comments-app-container';
    }

    static getComments() {
        return axios.get(`${this.getApiBaseUrl()}/react-comments/comments/${window.commentsAppNid}?_format=json`, {withCredentials: true}).catch((err) => {
            throw new Error(this.formatErrorMessage(err));
        });
    }

    static getMe() {
        return axios.get(`${this.getApiBaseUrl()}/react-comments/me?_format=json`, {withCredentials: true}).catch((err) => {
            throw new Error(this.formatErrorMessage(err));
        });
    }

    static postComment(commentText, anonName, anonEmail) {
        if (!commentText || !commentText.trim()) {
            return Promise.reject({message: window.Drupal.t('Comments cannot be empty.')});
        }

        return this.createCsrfPromise()
            .then((csrf) => {
                return axios({
                    method: 'post',
                    headers: {'X-CSRF-Token': csrf},
                    url: `${this.getApiBaseUrl()}/react-comments/comments/${window.commentsAppNid}?_format=json`,
                    data: {
                        reply_comment_id: 0,
                        comment: commentText,
                        anon_name: anonName,
                        anon_email: anonEmail
                    },
                    withCredentials: true
                })
            }).then((response) => {
                return response.data;
            }).catch((err) => {
                throw new Error(this.formatErrorMessage(err));
            });
    }

    static postReply(commentId, commentText, anonName, anonEmail) {
        if (!commentText || !commentText.trim()) {
            return Promise.reject({message: window.Drupal.t('Comments cannot be empty.')});
        }

        return this.createCsrfPromise()
            .then((csrf) => {
                return axios({
                    method: 'post',
                    headers: {'X-CSRF-Token': csrf},
                    url: `${this.getApiBaseUrl()}/react-comments/comments/${window.commentsAppNid}?_format=json`,
                    data: {
                        reply_comment_id: commentId,
                        comment: commentText,
                        anon_name: anonName,
                        anon_email: anonEmail
                    },
                    withCredentials: true
                });
            }).then((response) => {
                return response.data;
            }).catch((err) => {
                throw new Error(this.formatErrorMessage(err));
            });
    }

    static saveEdit(commentId, commentText) {
        if (!commentText || !commentText.trim()) {
            return Promise.reject({message: window.Drupal.t('Comments cannot be empty.')});
        }

        return this.createCsrfPromise()
            .then((csrf) => {
                return axios({
                    method: 'patch',
                    headers: {'X-CSRF-Token': csrf},
                    url: `${this.getApiBaseUrl()}/react-comments/comment/${commentId}?_format=json`,
                    data: {
                        comment: commentText
                    },
                    withCredentials: true
                });
            }).then((response) => {
                return response.data;
            }).catch((err) => {
                throw new Error(this.formatErrorMessage(err));
            });
    }

    static deleteComment(commentId) {
        return this.createCsrfPromise()
            .then((csrf) => {
                return axios({
                    method: 'delete',
                    headers: {'X-CSRF-Token': csrf},
                    data: {
                        '_format': 'json'
                    },
                    url: `${this.getApiBaseUrl()}/react-comments/comment/${commentId}?_format=json`,
                    withCredentials: true
                });
            }).then((response) => {
                return response.data;
            }).catch((err) => {
                throw new Error(this.formatErrorMessage(err));
            });
    }

    static flagComment(commentId) {
        return this.putComment(commentId, 'flag');
    }

    static publishComment(commentId) {
        return this.putComment(commentId, 'publish');
    }

    static unpublishComment(commentId) {
        return this.putComment(commentId, 'unpublish');
    }

    static putComment(commentId, op) {
        return this.createCsrfPromise()
            .then((csrf) => {
                return axios({
                    method: 'put',
                    headers: {'X-CSRF-Token': csrf},
                    data: {
                        'op': op
                    },
                    url: `${this.getApiBaseUrl()}/react-comments/comment/${commentId}?_format=json`,
                    withCredentials: true
                });
            }).then((response) => {
                return response.data;
            }).catch((err) => {
                throw new Error(this.formatErrorMessage(err));
            });
    }

    static formatErrorMessage(err) {
        if (err.response && err.response.data && err.response.data.message) {
            return err.response.data.message;
        }

        // Axios will return the hard-coded string "Network Error" if there is an
        // error processing the request.
        // (see https://github.com/axios/axios/blob/9a78465a9268dcd360d7663de686709a68560d3d/lib/adapters/xhr.js#L81)
        if (err.message === 'Network Error') {
            return 'Your Internet connection appears to be offline. Check your network settings and try again.';
        }

        return err.message;
    }
}
