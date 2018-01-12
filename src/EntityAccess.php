<?php

namespace Drupal\workspace;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service wrapper for hooks relating to entity access control.
 *
 * @internal
 */
class EntityAccess implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workspace manager service.
   *
   * @var \Drupal\workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a new EntityAccess instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\workspace\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspace_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('workspace.manager')
    );
  }

  /**
   * Implements a hook bridge for hook_entity_access().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check access for.
   * @param string $operation
   *   The operation being performed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account making the to check access for.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The result of the access check.
   *
   * @see hook_entity_access()
   */
  public function entityOperationAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Workspaces themselves are handled by their own access handler and we
    // should not try to do any access checks for entity types that can not
    // belong to a workspace.
    if ($entity->getEntityTypeId() === 'workspace' || !$this->workspaceManager->entityTypeCanBelongToWorkspaces($entity->getEntityType())) {
      return AccessResult::neutral();
    }

    return $this->bypassAccessResult($account);
  }

  /**
   * Implements a hook bridge for hook_entity_create_access().
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account making the to check access for.
   * @param array $context
   *   The context of the access check.
   * @param string $entity_bundle
   *   The bundle of the entity.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The result of the access check.
   *
   * @see hook_entity_create_access()
   */
  public function entityCreateAccess(AccountInterface $account, array $context, $entity_bundle) {
    // Workspaces themselves are handled by their own access handler and we
    // should not try to do any access checks for entity types that can not
    // belong to a workspace.
    $entity_type = $this->entityTypeManager->getDefinition($context['entity_type_id']);
    if ($entity_type->id() === 'workspace' || !$this->workspaceManager->entityTypeCanBelongToWorkspaces($entity_type)) {
      return AccessResult::neutral();
    }

    return $this->bypassAccessResult($account);
  }

  /**
   * Checks the 'bypass' permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account making the to check access for.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The result of the access check.
   */
  protected function bypassAccessResult(AccountInterface $account) {
    // This approach assumes that the current "global" active workspace is
    // correct, ie, if you're "in" a given workspace then you get ALL THE PERMS
    // to ALL THE THINGS! That's why this is a dangerous permission.
    $active_workspace = $this->workspaceManager->getActiveWorkspace();

    return AccessResult::allowedIfHasPermission($account, 'bypass entity access workspace ' . $active_workspace->id())->addCacheableDependency($active_workspace)
      ->orIf(
        AccessResult::allowedIf($active_workspace->getOwnerId() == $account->id())->cachePerUser()->addCacheableDependency($active_workspace)
          ->andIf(AccessResult::allowedIfHasPermission($account, 'bypass entity access own workspace'))
      );
  }

  /**
   * Returns an array of workspace-specific permissions.
   *
   * @return array
   *   The workspace permissions.
   */
  public function workspacePermissions() {
    $perms = [];

    foreach ($this->entityTypeManager->getStorage('workspace')->loadMultiple() as $workspace) {
      /** @var \Drupal\workspace\WorkspaceInterface $workspace */
      $perms += $this->createWorkspaceBypassPermission($workspace);
    }

    return $perms;
  }

  /**
   * Derives the delete permission for a specific workspace.
   *
   * @param \Drupal\workspace\WorkspaceInterface $workspace
   *   The workspace from which to derive the permission.
   *
   * @return array
   *   A single-item array with the permission to define.
   */
  protected function createWorkspaceBypassPermission(WorkspaceInterface $workspace) {
    $perms['bypass entity access workspace ' . $workspace->id()] = [
      'title' => $this->t('Bypass content entity access in %workspace workspace', ['%workspace' => $workspace->label()]),
      'description' => $this->t('Allow all Edit/Update/Delete permissions for all content in the %workspace workspace', ['%workspace' => $workspace->label()]),
      'restrict access' => TRUE,
    ];

    return $perms;
  }

}
