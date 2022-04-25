<?php

namespace Drupal\annotator\Plugin\rest\resource;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\annotator\XapiVerbAnnotation;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi_reporting\XapiStatementCreator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Annotation Resource.
 *
 * @RestResource(
 *   id = "annotations_resource",
 *   label = @Translation("Annotations Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/annotations"
 *   }
 * )
 */
class AnnotationsResource extends ResourceBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The statement creator.
   *
   * @var \Drupal\xapi_reporting\XapiStatementCreator
   */
  protected $statementCreator;

  /**
   * The LRS Request Generator.
   *
   * @var \Drupal\xapi\LRSRequestGenerator
   */
  protected $lrsRequestGenerator;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user instance.
   * @param Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   * @param \Drupal\xapi_reporting\XapiStatementCreator $statement_creator
   *   The statement creator.
   * @param Drupal\xapi\LRSRequestGenerator $lrs_request_generator
   *   The  LRS Request Generator.
   */
  public function __construct(array $configuration,
  $plugin_id,
  $plugin_definition,
  array $serializer_formats,
  LoggerInterface $logger,
  AccountProxyInterface $current_user,
  Request $current_request,
  XapiStatementCreator $statement_creator,
  LRSRequestGenerator $lrs_request_generator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->currentRequest = $current_request;
    $this->statementCreator = $statement_creator;
    $this->lrsRequestGenerator = $lrs_request_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('example_rest'),
      $container->get('current_user'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('xapi_reporting.xapi_statement_creator'),
      $container->get('lrs.request_generator')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   JSON list of annotations.
   */
  public function get() {
    // Get actor from request.
    $statement = $this->statementCreator->getTemplateStatement()->jsonSerialize();
    $actor = $statement["actor"];

    // Get all statments from actor with verb annotated.
    $verb = XapiVerbAnnotation::annotated();
    $response = $this->lrsRequestGenerator->getStatementsByActor($actor, $verb);
    $statements = \json_decode($response->getContent());
    if (!isset($statements->statements)) {
      return new JsonResponse([]);
    }
    $statements = $statements->statements;
    $annotations = [];
    $node_ids = [];

    // Loop through statements to build nice response.
    foreach ($statements as $statement) {
      // Filter out void statements.
      if (!isset($statement->object) || !isset($statement->object->definition)) {
        continue;
      }
      $empty_string = "";
      $node_url = $statement->object->id;
      $node_id = "";
      $node_title = "";

      // Build a URL from the object.
      // This does not build if the node is deleted (feature).
      try {
        $full_url = Request::create($node_url);
        $url = Url::fromUserInput($full_url->getPathInfo())->getRouteParameters();
        if (!empty($url)) {
          $node_id = $url["node"];
        }
      }
      catch (\Exception $exception) {
      }

      // Parse the statement to get the annotation information.
      $text = isset($statement->result->response) ? $statement->result->response : $empty_string;
      $quote = isset($statement->object->definition->extensions->{$node_url . '/highlight/value'}) ?
        $statement->object->definition->extensions->{$node_url . '/highlight/value'} :
        $empty_string;
      $fullDate = new DrupalDateTime($statement->timestamp);
      $date = $fullDate->format('m/d/Y');

      // Get the object name definition array (key is the language code).
      $name_definition = (array) $statement->object->definition->name;
      $languages = $this->currentRequest->getLanguages();
      array_push($languages, 'en');
      array_push($languages, 'en-US');

      // Loop through the langauges in the request header (and en/en-US)
      // until you find the name of the object.
      // Note this will be overwritten later if the node still exists.
      foreach ($languages as $language) {
        if (isset($name_definition[$language])) {
          $node_title = $name_definition[$language];
          break;
        }
      }
      $annotation = [
        "date" => $date,
        "statement_id" => $statement->id,
        "node_id" => $node_id,
        "node_title" => $node_title,
        "node_url" => $node_url,
        "text" => $text,
        "quote" => $quote,
      ];

      // Push the node id so we can look up node information all at once.
      // (One query is better than several)
      array_push($annotations, $annotation);
      if (!empty($node_id)) {
        array_push($node_ids, $node_id);
      }
    }

    // Update node_title to whatever the node title is
    // if it exists.
    // Note this is an easy place to pass the entire node.
    if (!empty($node_ids)) {
      $nodes = Node::loadMultiple($node_ids);
      foreach ($annotations as &$annotation) {
        if (!isset($annotation["node_id"]) || !isset($nodes[$annotation["node_id"]])) {
          continue;
        }
        $node = $nodes[$annotation["node_id"]];
        $node_title = $node->getTitle();
        $annotation["node_title"] = $node_title;
      }
    }

    return new JsonResponse($annotations);
  }

}
