<?php

namespace Drupal\workspace;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\multiversion\Entity\WorkspaceInterface;

/**
 * Service wrapper for hooks relating to entity access control.
 */
class EntityAccess {
  use StringTranslationTrait;

  /**
   * @var int
   *
   * The ID of the default workspace, which has special permission handling.
   */
  protected $defaultWorkspaceId;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EntityAccess.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param int $default_workspace
   *   The ID of the default workspace.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, $default_workspace) {
    $this->defaultWorkspaceId = $default_workspace;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Hook bridge;
   *
   * @see hook_entity_access()
   * @see hook_ENTITY_TYPE_access()
   *
   * @param WorkspaceInterface $workspace
   * @param string $operation
   * @param AccountInterface $account
   *
   * @return AccessResult
   */
  public function workspaceAccess(WorkspaceInterface $workspace, $operation, AccountInterface $account) {

    $operations = [
      'view' => ['any' => 'view_any_workspace', 'own' => 'view_own_workspace'],
      'update' => ['any' => 'edit_any_workspace', 'own' => 'edit_own_workspace'],
      'delete' => ['any' => 'delete_any_workspace', 'own' => 'delete_own_workspace'],
    ];

    // The default workspace is always viewable, no matter what.
    $result = AccessResult::allowedIf($operation == 'view' && $workspace->id() == $this->defaultWorkspaceId)
      // Or if the user has permission to access any workspace at all.
      ->orIf(AccessResult::allowedIfHasPermission($account, $operations[$operation]['any']))
      // Or if it's their own workspace, and they have permission to access their own workspace.
      ->orIf(
        AccessResult::allowedIf($workspace->getOwnerId() == $account->id())
          ->andIf(AccessResult::allowedIfHasPermission($account, $operations[$operation]['own']))
      )
      ->orIf(AccessResult::allowedIfHasPermission($account, $operation . '_workspace_' . $workspace->id()));

    return $result;
  }

  /**
   * Hook bridge;
   *
   * @see hook_create_access();
   * @see hook_ENTITY_TYPE_create_access().
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param array $context
   * @param $entity_bundle
   *
   * @return AccessResult
   */
  public function workspaceCreateAccess(AccountInterface $account, array $context, $entity_bundle) {
    return AccessResult::allowedIfHasPermission($account, 'create_workspace');
  }

  /**
   * Returns an array of workspace-specific permissions.
   *
   * Note: This approach assumes that a site will have only a small number
   * of workspace entities, under a dozen. If there are many dozens of
   * workspaces defined then this approach will have scaling issues.
   *
   * @return array
   *   The workspace permissions.
   */
  public function workspacePermissions() {
    $perms = [];

    foreach ($this->getAllWorkspaces() as $workspace) {
      $perms += $this->createWorkspaceViewPermission($workspace)
      + $this->createWorkspaceEditPermission($workspace)
      + $this->createWorkspaceDeletePermission($workspace);
    }

    return $perms;
  }

  /**
   * Returns a list of all workspace entities in the system.
   *
   * @return WorkspaceInterface[]
   */
  protected function getAllWorkspaces() {
    return $this->entityTypeManager->getStorage('workspace')->loadMultiple();
  }

  /**
   * Derives the view permission for a specific workspace.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace from which to derive the permission.
   * @return array
   *   A single-item array with the permission to define.
   */
  protected function createWorkspaceViewPermission(WorkspaceInterface $workspace) {
    $perms['view_workspace_' . $workspace->id()] = [
      'title' => $this->t('View the %workspace workspace', ['%workspace' => $workspace->label()]),
      'description' => $this->t('View the %workspace workspace and content within it', ['%workspace' => $workspace->label()]),
    ];

    return $perms;
  }

  /**
   * Derives the edit permission for a specific workspace.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace from which to derive the permission.
   * @return array
   *   A single-item array with the permission to define.
   */
  protected function createWorkspaceEditPermission(WorkspaceInterface $workspace) {
    $perms['update_workspace_' . $workspace->id()] = [
      'title' => $this->t('Edit the %workspace workspace', ['%workspace' => $workspace->label()]),
      'description' => $this->t('Edit the %workspace workspace itself', ['%workspace' => $workspace->label()]),
    ];

    return $perms;
  }

  /**
   * Derives the delete permission for a specific workspace.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace from which to derive the permission.
   * @return array
   *   A single-item array with the permission to define.
   */
  protected function createWorkspaceDeletePermission(WorkspaceInterface $workspace) {
    $perms['delete_workspace_' . $workspace->id()] = [
      'title' => $this->t('Delete the %workspace workspace', ['%workspace' => $workspace->label()]),
      'description' => $this->t('View the %workspace workspace and all content within it', ['%workspace' => $workspace->label()]),
    ];

    return $perms;
  }

}