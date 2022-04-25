<?php

/**
 * Implements hook_views_data().
 */
function react_comments_views_data() {
  $data = [];
  $data['react_comments_status'] = [];
  $data['react_comments_status']['table'] = [];
  $data['react_comments_status']['table']['group'] = t('React Comments');
  $data['react_comments_status']['table']['provider'] = 'react_comments';
  $data['react_comments_status']['table']['base'] = [
    'field'  => 'cid',
    'title'  => t('React Comments: ID'),
    'help'   => t('React Comments: Comments'),
    'weight' => -10,
  ];
  $data['react_comments_status']['table']['join'] = [
    'comment_field_data' => [
      'left_field' => 'cid',
      'field'      => 'cid',
    ],
    'react_comments_status' => [
      'left_field' => 'cid',
      'field'      => 'cid',
    ],
  ];
  $data['react_comments_status']['cid'] = [
    'title'        => t('Comment Status'),
    'help'         => t('Comment Status'),
    'relationship' => [
      'base'       => 'comment_field_data',
      'base field' => 'cid',
      'id'         => 'standard',
      'label'      => t('Comment Status'),
    ],
  ];
  $data['react_comments_status']['uid'] = [
    'title'        => t('User'),
    'help'         => t('User'),
    'relationship' => [
      'base'       => 'users_field_data',
      'base field' => 'uid',
      'id'         => 'standard',
      'label'      => t('User'),
    ],
  ];
  $data['react_comments_status']['status'] = [
    'title' => t('React Comment Status'),
    'help'  => t('React Comment Status'),
    'field' => [
      'id' => 'react_comment_status',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'react_comment_status',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
  ];
  $data['react_comments_status']['created_at'] = [
    'title' => t('Status Created At'),
    'help'  => t('Timestamp'),
    'field' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'argument' => [
      'id' => 'date',
    ],
  ];
  return $data;
}
