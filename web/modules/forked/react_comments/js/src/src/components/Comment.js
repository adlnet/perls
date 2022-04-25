import React, { Component } from 'react';
import CommentBox from './CommentBox';
import './Comment.css';
import timeago from 'timeago.js';
import Constants from '../utils/constants';
import ReplyArrow from '../icons/reply-arrow';
import DeletedComment from './DeletedComment';
import ReplyList from './ReplyList';
import CommentActions from './CommentActions';
import CommentMenu from './CommentMenu';

class Comment extends Component {
  state = {
    replyActive: false,
    editActive: false,
    message: null
  };

  toggleState = (stateToToggle) => {
    this.setState({[stateToToggle]: !this.state[stateToToggle]});
  };

  postReply = (commentText, anonName, anonEmail) => {
    return this.props.postReply(this.props.id, commentText, anonName, anonEmail);
  };

  saveEdit = (commentText) => {
    return this.props.saveEdit(this.props.id, commentText);
  };

  deleteComment = (id) => {
    return this.props.deleteComment(id).catch((err) => {
      this.setState({
        message: {
          message: err.message,
          type: 'error'
        }
      })
    });
  };

  closeMenu = () => {
    if (this.props.id === this.props.openMenu) {
      this.props.toggleMenu(this.props.id);
    }
  };

  convertTimestampToDate = (created_at) => {
    const date = new Date(created_at * 1000);
    const created_ago = timeago().format(created_at * 1000);

    // if the comment was posted more than a week ago show the date instead
    if (Date.now() - created_at * 1000 > 604800000) {
      return `${date.getMonth() + 1}/${date.getDate()}/${date.getFullYear()}`;
    }

    // If it seconds/minutes/hours/days ago
    const created_ago_parts = created_ago.match(/(\d+)\s([a-zA-Z]+)\sago/);
    if (created_ago_parts) {
      let created_ago_time = created_ago_parts[2];

      switch (created_ago_parts[2]) {
        // We need to list all options using Drupal.t, to be able to translate
        case 'day': created_ago_time = window.Drupal.t('day'); break;
        case 'days': created_ago_time = window.Drupal.t('days'); break;
        case 'hour': created_ago_time = window.Drupal.t('hour'); break;
        case 'hours': created_ago_time = window.Drupal.t('hours'); break;
        case 'minute': created_ago_time = window.Drupal.t('minute'); break;
        case 'minutes': created_ago_time = window.Drupal.t('minutes'); break;
        case 'second': created_ago_time = window.Drupal.t('second'); break;
        case 'seconds': created_ago_time = window.Drupal.t('seconds'); break;
      }

      return window.Drupal.t('@created_ago ago', {
        '@created_ago': +created_ago_parts[1] + ' ' + created_ago_time
      });
    }

    // Just now
    return window.Drupal.t('just now');
  };

  render() {
    const { id, currentUser, settings, comment, replies, replyTo, isReplyAllowed, created_at, openMenu, toggleMenu, status, flagComment, publishComment, unpublishComment, published, name} = this.props;
    const { replyActive, editActive, message } = this.state;

    let { user } = this.props;

    if (user.isAnon) {
      user.name = name;
    }

    if (status === Constants.deleted || status === Constants.flaggedUnpublished) {
      return (
          <DeletedComment {...this.props} />
      );
    }

    return (
      <div className={editActive ? "rc_comment rc_comment--edit-active" : "rc_comment"}>
        <div className={ (published === '0') ? "rc_comment-container rc_comment-container--unpublished" : "rc_comment-container" }>
          <div className="rc_avatar">
            <div className="rc_avatar__image-wrapper">
              <img alt={window.Drupal.t('User avatar')} src={user.thumbnail}/>
            </div>
          </div>
          <div className="rc_body">
            { message && <div className={`rc_message rc_message-type--${message.type}`}>{message.message}</div> }
            <div className="rc_comment-details">
              <span className="rc_username">{user.name}</span>
              { replyTo && <span className="rc_reply-to"><ReplyArrow/>{replyTo.name}</span> }

              <CommentMenu
                  user={user}
                  currentUser={currentUser}
                  id={id}
                  openMenu={openMenu}
                  closeMenu={this.closeMenu}
                  toggleMenu={toggleMenu}
                  flagComment={flagComment}
                  publishComment={publishComment}
                  unpublishComment={unpublishComment}
                  deleteComment={this.deleteComment}
                  status={status}
                  published={published}
              />
            </div>

            { editActive ?
              <CommentBox
                commentId={id}
                settings={settings}
                text={comment}
                user={currentUser}
                isEdit={true}
                type="edit"
                cancelEdit={() => this.toggleState('editActive')}
                saveEdit={this.saveEdit}
              /> :
              <div className="rc_comment-text" dangerouslySetInnerHTML={{__html: comment.replace(/(?:\r\n|\r|\n)/g, '<br />')}}></div>
            }

            <div className="rc_comment-footer">
              {/* Apparently javascript uses 13 digit timestamps (including milliseconds)... Append 000 to the unix timestamp to get it to work. */}
              <div className="rc_time-ago">{this.convertTimestampToDate(created_at)}</div>

              <CommentActions
                currentUser={currentUser}
                user={user}
                editActive={editActive}
                replyActive={replyActive}
                isReplyAllowed={isReplyAllowed}
                toggleState={this.toggleState}
              />
            </div>

            { replyActive &&
              <CommentBox
                commentId={id}
                isReply={true}
                type="reply"
                user={currentUser}
                settings={settings}
                postReply={this.postReply}
                closeReplyBox={() => this.toggleState('replyActive')}
              /> }

          </div>
        </div>
        { replies &&
          <ReplyList
              {...this.props}
              replyTo={user}
          />
        }
      </div>
    );
  }
}

export default Comment;
