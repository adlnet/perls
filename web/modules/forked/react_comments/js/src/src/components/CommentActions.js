import React from 'react';

export default ({currentUser, user, editActive, replyActive, toggleState, isReplyAllowed}) => {
    const userCanEdit = currentUser && currentUser.hasPermission('edit own comments') && !currentUser.isAnon && currentUser.id === user.id;
    const userCanAdministerComments = currentUser && currentUser.hasPermission('administer comments');

    return (
        <div className="rc_actions-wrapper">
            <ul>
                { (userCanEdit || userCanAdministerComments) &&
                <li><button onClick={() => toggleState('editActive')} className={editActive ? "rc_edit rc_edit--active" : "rc_edit"}>{window.Drupal.t('Edit')}</button></li>
                }
                {   isReplyAllowed &&
                    <li>
                        <button
                          onClick={(e) => { toggleState('replyActive'); }}
                          className={replyActive ? "rc_reply rc_reply--active" : "rc_reply"}
                        >
                            {window.Drupal.t('Reply')}
                        </button>
                    </li>
                }
            </ul>
        </div>
    )
};
