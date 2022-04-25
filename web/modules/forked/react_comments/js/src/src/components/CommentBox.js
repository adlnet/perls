import React, { Component } from 'react';
import './CommentBox.css';
import Throbber from 'react-throbber';
import ReactQuill from 'react-quill';
import 'react-quill/dist/quill.snow.css';
import Constants from '../utils/constants';
import validateEmail from '../utils/validateEmail';
import DOMPurify from 'dompurify';

// Limit chat max characters.
const MAX_LENGTH = 300;

class CommentBox extends Component {
  state = {
    isOpen: false,
    isFocused: false,
    isLoading: false,
    message: false,
    messageOnly: false,
    anonName: false,
    anonEmail: false,
    text: (this.props.type === 'edit') ? this.props.text.replace(/\r\n?|\n/g, '<br />') : ''
  };

  getLoginLink = () => {
    return `/user/login?destination=${encodeURIComponent(window.location.pathname)}%23comments-app-container`;
  };

  toggleFocus = () => {
    this.setState({
      isFocused: !this.state.isFocused,
      isOpen: !this.state.isOpen
    });
  };

  getCommentText = () => {
    // Trim the default text (non html) to evaluate if this is empty.
    return DOMPurify.sanitize(this.commentBox.getEditor().getText()).trim();
  };

  validateCommentLength = () => {
    if (this.getCommentText().length <= MAX_LENGTH) {
      this.setState({message: false});
      return true;
    }

    this.setState({
      message: {
        message: window.Drupal.t('Your comment is too long (it can only be @max characters long). Current characters count: @current', {'@max': MAX_LENGTH, '@current': this.getCommentText().length}),
        type: 'error'
      },
    });

    return false;
  };

  postComment = (commentText) => {
    if (this.state.isLoading) return;

    if (!this.userCanPost()) return;

    if (!this.validateCommentLength()) return;

    this.setState({ isLoading: true, message: false });
    this.props.postComment(commentText, this.state.anonName, this.state.anonEmail).then((response) => {

      if (response.code === 'success') {
        this.setState({
          isLoading: false,
          text: '',
        });
      }
      else if (response.code === 'queued_for_moderation') {
        this.setState({
          message: {
            message: response.message,
            type: 'success'
          },
          isLoading: false,
          text: '',
        });
      }
    }).catch((error) => {
      const message = {
        message: error.message,
        type: 'error'
      };
      this.setState({ isLoading: false, message: message });
    });
  };

  postReply = (commentText) => {
    if (this.state.isLoading) return;

    if (!this.userCanPost()) return;

    if (!this.validateCommentLength()) return;

    this.setState({ isLoading: true, message: false });
    this.props.postReply(commentText, this.state.anonName, this.state.anonEmail).then((response) => {
      if (response.code === 'success') {
        this.setState({ isOpen: false, message: false });
        this.props.closeReplyBox();
      }
      else if (response.code === 'queued_for_moderation') {
        const message = {
          message: response.message,
          type: 'success'
        };
        this.setState({ message: message, messageOnly: true, isLoading: false });
      }
    }).catch((error) => {
      const message = {
        message: error.message,
        type: 'error'
      };
      this.setState({ isLoading: false, message: message });
    });
  };

  saveEdit = (commentText) => {
    if (this.state.isLoading) return;

    if (!this.userCanPost()) return;

    this.setState({ isLoading: true, message: false });
    this.props.saveEdit(commentText).then(() => {
      this.props.cancelEdit();
    }).catch((error) => {
      const message = {
        message: error.message,
        type: 'error'
      };
      this.setState({ isLoading: false, message: message });
    });
  };

  userCanPost = () => {
    const {user, settings} = this.props;
    const isAnon = user && user.isAnon;
    const anonSetting = settings.anonymous;

    if (isAnon && !this.state.anonName) {
      this.setState({ message: { message: window.Drupal.t('Please provide your name or alias to post as a guest'), type: 'error'}});
      return false;
    }
    else if (isAnon && !this.state.anonEmail && (anonSetting === Constants.anonMustContact)) {
      this.setState({ message: { message: window.Drupal.t('Please provide your email to post as a guest'), type: 'error'}});
      return false;
    }
    else if (isAnon && this.state.anonEmail && !validateEmail(this.state.anonEmail)) {
      this.setState({ message: { message: window.Drupal.t('Please provide a valid email address'), type: 'error'}});
      return false;
    }
    else {
      return true;
    }
  };

  handleAnonFormChange = (e) => {
    if (e.target.value && e.target.id === 'rc_name') {
      this.setState({anonName: e.target.value});
    }
    else if (e.target.value && e.target.id === 'rc_email') {
      this.setState({anonEmail: e.target.value});
    }
    else if (e.target.id === 'rc_name') {
      this.setState({anonName: false});
    }
    else {
      this.setState({anonEmail: false});
    }
  };

  componentDidMount() {
    if (this.props.user && this.props.type === 'reply') {
      const x = window.scrollX;
      const y = window.scrollY;
      this.commentBox.focus();
      window.scrollTo(x, y);
    }
  }

  render() {
    const { user, settings, type, cancelEdit } = this.props;
    const { isOpen, isFocused, isLoading, message, messageOnly, text } = this.state;

    let containerClasses = ['rc_comment-box-container'];
    if (isOpen || type === 'reply' || type === 'edit' ) containerClasses.push('rc_is-open');
    if (isLoading) containerClasses.push('rc_is-loading');
    if (messageOnly) containerClasses.push('rc_message-only');
    if (type === 'reply') containerClasses.push('rc_is-reply');
    if (type === 'edit') containerClasses.push('rc_is-edit');
    containerClasses = containerClasses.join(' ');

    const showLoginButton = !user || user.isAnon;
    const userCanPostComments = user && user.hasPermission('post comments');

    return (
      <div className={containerClasses}>
        { type !== 'edit' &&
          <div className='rc_comment-box-avatar'>
            <div className="rc_comment-box-avatar__image-wrapper">
              <img alt={window.Drupal.t('User avatar')} src={ (user && user.thumbnail) || Constants.defaultAvatarUrl }/>
            </div>
          </div>
        }
        <div className="rc_input-outer-wrapper">
          { message && <div className={`rc_message rc_message-type--${message.type}`}>{message.message}</div> }
          <div className="rc_throbber-wrapper">
            { isLoading && <Throbber size="25px"/> }
            <div className="rc_input-wrapper">
              <ReactQuill
                placeholder={ !isFocused && (type !== 'edit') ? window.Drupal.t('Join the discussion...') : '' }
                value={text}
                theme="snow"
                ref={(commentBox) => this.commentBox = commentBox}
                onChange={(text) => this.setState({ text }, () => this.validateCommentLength())}
                onFocus={this.toggleFocus}
                onBlur={this.toggleFocus}
                modules={{toolbar: false}}
              />
              <div className="rc_input-actions">
                { type === 'edit' &&
                <span>
                <button onClick={() => cancelEdit()} className="rc_cancel-comment">{window.Drupal.t('Cancel')}</button>
                <button onClick={() => this.saveEdit(this.getCommentText())} className="rc_add-comment">{window.Drupal.t('Save Edit')}</button>
              </span>
                }
                { (type === 'reply' && user && userCanPostComments) && <button onClick={() => this.postReply(this.getCommentText()) } className="rc_add-comment">{user.name ? window.Drupal.t('Post as @name', {'@name':user.name}) : window.Drupal.t('Post')}</button> }
                { (type === 'comment' && user && userCanPostComments) && <button onClick={() => this.postComment(this.getCommentText())} className="rc_add-comment">{user.name ? window.Drupal.t('Post as @name', {'@name':user.name}) : window.Drupal.t('Post')}</button> }
              </div>
            </div>
          </div>

          { showLoginButton && <div className="rc_anon-wrapper">
            <div>
              <label htmlFor="rc_login-button">{window.Drupal.t('log in to comment')}</label>
              <a id="rc_login-button" className="rc_login-button" href={this.getLoginLink()}>{window.Drupal.t('Log in')}</a>
            </div>
            { userCanPostComments && <div className="rc_anon-form">
              <div>
                <label htmlFor="rc_name">{window.Drupal.t('or post as a guest')}</label>
                <div className="rc_anon-form-input-wrapper">
                  <input onChange={this.handleAnonFormChange} id="rc_name" type="text" placeholder={window.Drupal.t('Name')}/>
                  { (settings.anonymous !== Constants.anonMayNotContact) &&
                    <input onChange={this.handleAnonFormChange} id="rc_email" type="text" placeholder={window.Drupal.t('Email')}/>
                  }
                </div>
              </div>
            </div> }
          </div> }
        </div>
      </div>
    );
  }
}

export default CommentBox;
