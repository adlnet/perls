<?php

/**
 * @file
 * Colors for core/color module.
 */

$info = [
  // Available colors and color labels used in theme.
  'fields' => [
    'primary' => t('Primary Color'),
    'secondary' => t('Secondary Color'),
    'tertiary' => t('Tertiary Color'),
    'quiz' => t('Quizzes/Tests'),
    'tip' => t('Tips'),
    'flash' => t('Flashcards'),
    'course' => t('Courses'),
    'podcast' => t('Podcasts'),
    // https://www.drupal.org/project/drupal/issues/1236098#comment-13215759
    'base' => t('Background Color'),
    'text' => t('Text Color'),
    'link' => t('Links'),
    'menu' => t('Menu background'),
  ],
  // Pre-defined color schemes.
  'schemes' => [
    'default' => [
      'title' => t('Blue Waves (default)'),
      'colors' => [
        'primary' => '#006686',
        'secondary' => '#26a1c6',
        'secondary_lighter' => '#47b9db',
        'secondary_darker' => '#1e809e',
        'tertiary' => '#d6e8ee',
        'tip' => '#dd031d',
        'flash' => '#fc990b',
        'quiz' => '#63378f',
        'course' => '#2588d0',
        'course_darker' => '#1d6ca6',
        'podcast' => '#5d5d5d',
        // https://www.drupal.org/project/drupal/issues/1236098#comment-13215759
        'text' => '#232323',
        'base' => '#ffffff',
        'menu' => '#0084ae',
      ],
    ],
    'grape' => [
      'title' => t('Grape'),
      'colors' => [
        'primary' => '#4e314c',
        'secondary' => '#442941',
        'secondary_lighter' => '#51314d',
        'secondary_darker' => '#362033',
        'tertiary' => '#8b5788',
        'tip' => '#da635b',
        'flash' => '#55984c',
        'quiz' => '#fe9d0b',
        'course' => '#8b5788',
        'course_darker' => '#6f456c',
        'podcast' => '#2777ab',
        // https://www.drupal.org/project/drupal/issues/1236098#comment-13215759
        'text' => '#000000',
        'base' => '#ffffff',
        'menu' => '#9c6197',
      ],
    ],
    'dark' => [
      'title' => t('Dark Mode'),
      'colors' => [
        'primary' => '#1e1f31',
        'secondary' => '#03bf9c',
        'secondary_lighter' => '#03e5bb',
        'secondary_darker' => '#02987c',
        'tertiary' => '#70727c',
        'tip' => '#e63349',
        'flash' => '#541edb',
        'quiz' => '#0098ce',
        'course' => '#109980',
        'course_darker' => '#0c7a66',
        'podcast' => '#ffa632',
        // https://www.drupal.org/project/drupal/issues/1236098#comment-13215759
        'text' => '#ffffff',
        'base' => '#121325',
        'menu' => '#3c3d62',
      ],
    ],
  ],
  'css' => [
    'dist/css/color.css',
    'dist/css/am4chart-theme.js',
  ],
  'preview_html' => 'color/preview.html',
  'preview_library' => 'perls/color.preview',
];
