<?php

namespace Drupal\perls_course_certificates\Plugin\Badge;

use Drupal\achievements\Entity\AchievementEntity as AchievementEntityOriginal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\flag\FlagServiceInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\badges\BadgePluginBase;
use Drupal\badges\Entity\AchievementEntity;
use Drupal\badges\ImageGenerationAchievementInterface;
use Drupal\badges\Service\BadgeService;
use Drupal\textimage\TextimageException;
use Drupal\textimage\TextimageFactoryInterface;
use Drupal\textimage\TextimageLogger;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Certificate generation for course completions.
 *
 * @\Drupal\badges\Annotation\Badge(
 *   id = "course_completion_certificates",
 *   label = @Translation("Course Completion Certificate"),
 *   description = @Translation("Certificate becomes unlocked when a course is completed."),
 * )
 */
class CourseCompletionCertificates extends BadgePluginBase implements ContainerFactoryPluginInterface, ImageGenerationAchievementInterface {

  /**
   * The badge Service.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected BadgeService $badgeService;

  /**
   * The flag Service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected FlagServiceInterface $flagService;

  /**
   * The entity type manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Text Image Factory service.
   *
   * @var \Drupal\textimage\TextimageFactoryInterface
   */
  protected TextimageFactoryInterface $textImageFactory;

  /**
   * Text Image logger service.
   *
   * @var \Drupal\textimage\TextimageLogger
   */
  protected TextimageLogger $textImageLogger;

  /**
   * File URL Generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   * */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Badge Plugin'),
      $container->get('badges.badge_service'),
      $container->get('flag'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('textimage.factory'),
      $container->get('textimage.logger'),
      $container->get('file_url_generator'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger,
    BadgeService $badge_service,
    FlagServiceInterface $flag_service,
    EntityTypeManagerInterface $entityTypeManager,
    DateFormatterInterface $data_formatter,
    TextimageFactoryInterface $text_image_factory,
    TextimageLogger $text_image_logger,
    FileUrlGeneratorInterface $file_url_generator,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->badgeService = $badge_service;
    $this->flagService = $flag_service;
    $this->entityTypeManager = $entityTypeManager;
    $this->dateFormatter = $data_formatter;
    $this->textImageFactory = $text_image_factory;
    $this->textImageLogger = $text_image_logger;
    $this->fileUrlGenerator = $file_url_generator;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    AchievementEntityOriginal $achievement = NULL
  ) {
    $entities = NULL;
    if (
      $achievement !== NULL
      && $achievement->getThirdPartySetting('badges', $this->getPluginId())
    ) {
      $entities = $this->getEntities($achievement);
    }

    return [
      '#type' => 'entity_autocomplete',
      '#target_type' => $this->getEntityType(),
      '#tags' => FALSE,
      '#validate_reference' => TRUE,
      '#maxlength' => 5000,
      '#selection_settings' => [
        'target_bundles' => $this->getEntityBundles(),
      ],
      '#title' => $this->t('Course to complete'),
      '#description' => $this->t('This certificate is awarded when a user completes this course.'),
      '#default_value' => $entities,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfigWithBadgeSettings(
    FormStateInterface $form_state,
    AchievementEntityOriginal $achievement
  ) {
    $data = [
      'entities' => $form_state->getValue($this->getPluginId()),
    ];
    $achievement->setThirdPartySetting('badges', $this->getPluginId(), $data);
  }

  /**
   * {@inheritdoc}
   */
  public function updateUserProgress(AccountInterface $user, array $updates = NULL) {
    // Award badge if a user has completed all the content linked to this badge.
    $badges = $this->badgeService->getBadgesByType($this->getPluginId());
    $unlocked_badges = $this->badgeService->getUnlockedBadges($user);
    $flag = $this->flagService->getFlagById('completed');
    // For each badge load the associated term with children.
    foreach ($badges as $badge_id => $badge) {
      // If already unlocked continue.
      if (isset($unlocked_badges[$badge_id])) {
        continue;
      }
      // Get associated term label.
      $entity = $this->getEntities($badge);
      if (!$entity) {
        continue;
      }
      $award = TRUE;
      if (!$this->flagService->getFlagging($flag, $entity, $user)) {
        // Required entity not flagged.
        $award = FALSE;
      }
      // If all entities are flagged award badge.
      if ($award) {
        $this->badgeService->awardBadge($user, $badge_id);
      }

    }
  }

  /**
   * Return the entity type this flag is used to on.
   *
   * @return string
   *   Returns the entity type string.
   */
  protected function getEntityType() {
    return 'node';
  }

  /**
   * Return the entity bundles this flag is used to on.
   *
   * @return string[]
   *   Returns the entity bundles this flag is used on.
   */
  protected function getEntityBundles(): array {
    return ['course' => 'course'];
  }

  /**
   * Get a list of entities that need to be flagged for badge to be awarded.
   *
   * @param \Drupal\achievements\Entity\AchievementEntity $achievement
   *   The achievement entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Returns a list of entities to be flagged or null if there is none.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntities(
    AchievementEntityOriginal $achievement
  ): ?EntityInterface {
    $previous_value = $achievement
      ->getThirdPartySetting('badges', $this->getPluginId())['entities'];

    return $this
      ->entityTypeManager
      ->getStorage($this->getEntityType())->load($previous_value);
  }

  /**
   * Generate Images for this achievement.
   */
  public function generateImage(
    AchievementEntity $achievement,
    UserInterface $user,
    $image_type
  ) {
    // We only generate images for unlocked images.
    if ($image_type === 'locked' || $image_type === 'secret') {
      return $this
        ->fileUrlGenerator
        ->generateAbsoluteString($achievement->getLockedPath());
    }
    // Need to generate different images for different styles.
    $image_style = ($image_type === 'sharable') ? 'certificate_full_view' : 'certificate_thumbnail';
    $user = User::load($user->id());
    $course = $this->getEntities($achievement);
    $unlock = $this->badgeService->getUnlockedBadges($user, $achievement->id());
    // If we haven't unlocked this achievement we should not generate images.
    if (!$unlock || !isset($unlock['timestamp'])) {
      return NULL;
    }
    $unlock_time = $this->dateFormatter->format($unlock['timestamp'], 'short');
    $unlock_time = explode('-', $unlock_time)[0];
    try {
      $bubbleable_metadata = new BubbleableMetadata();
      $background_file = $this
        ->entityTypeManager
        ->getStorage('file')
        ->loadByProperties(['uri' => $achievement->getUnlockedPath()]);
      $background_file = reset($background_file);
      if (!$background_file) {
        $background_file = File::create(
          [
            'uri' => $achievement->getUnlockedPath(),
          ]);
      }
      $image = $this
        ->textImageFactory
        ->get($bubbleable_metadata)
        ->setSourceImageFile($background_file)
        ->setStyle(ImageStyle::load($image_style))
        ->process(
          [
            $user->getDisplayName(),
            $course ? $course->label() . ' ' : '',
            $unlock_time,
            $user->uuid(),
          ])
        ->buildImage();
      $url = $image ? $image->getURL()->toString() : '';
    }
    catch (TextimageException $e) {
      $this
        ->textImageLogger
        ->error($this->t('Failed to build a Textimage image. Error: @message', [
          '@message' => $e->getMessage(),
        ]));
      // If we fail to build text image, return default unlocked image.
      $url = $this
        ->fileUrlGenerator
        ->generateAbsoluteString($achievement->getUnLockedPath());
    }

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultImage($type) {
    if ($type !== 'unlocked') {
      return NULL;
    }

    return $this
      ->configFactory
      ->get('achievements.settings')
      ->get('course_completion_certificate_image.path') ?: 'public://badges/certificate.png';
  }

}
