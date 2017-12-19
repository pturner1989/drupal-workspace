<?php

namespace Drupal\workspace;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * RepositoryHandler plugins handle content replication.
 *
 * The replication will use data from the target repository handler plugin to
 * merge the content between the source and the target. For example an internal
 * replication might just need the workspace IDs, but a contrib module
 * performing an external replication may need hostname, port, username,
 * password etc.
 */
interface RepositoryHandlerInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Default empty value for repository handler fields.
   */
  const EMPTY_VALUE = '_none';

  /**
   * Returns the label of the repository handler.
   *
   * This is used as a form label where a user selects the replication target.
   *
   * @return string
   *   The label text, which could be a plain string or an object that can be
   *   cast to a string.
   */
  public function getLabel();

  /**
   * Returns the repository handler plugin description.
   *
   * @return string
   *   The description text, which could be a plain string or an object that can
   *   be cast to a string.
   */
  public function getDescription();

  /**
   * Returns whether the repository handler is remote or not.
   *
   * @return bool
   *   TRUE if the repository handler is remote, FALSE otherwise.
   */
  public function isRemote();

}