import React from 'react';
import ReplyList from './ReplyList';
import Constants from '../utils/constants';

export default (props) => {
    const { user } = props;
    return (
    <div className='rc_comment rc_comment--deleted'>
        <div className="rc_comment-container">
            <div className="rc_avatar">
              <div className="rc_avatar__image-wrapper">
                <img alt={window.Drupal.t('User avatar')} src={ (user && user.thumbnail) || Constants.defaultAvatarUrl }/>
              </div>
            </div>
            <div className="rc_body">
                <div className="rc_comment-details">
                    {window.Drupal.t('This comment has been deleted.')}
                </div>
            </div>
        </div>
        { props.replies &&
        <ReplyList
            {...props}
            replyTo={props.user}
        />
        }
    </div>
    );
};
