<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi Role Repository
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Core\Entity;
use Ushahidi\Core\SearchData;
use Ushahidi\Core\Entity\Role;
use Ushahidi\Core\Entity\RoleRepository;

class Ushahidi_Repository_Role extends Ushahidi_Repository implements
	RoleRepository
{
	// Ushahidi_Repository
	protected function getTable()
	{
		return 'roles';
	}

	protected function getPermissions($id)
	{
		return DB::select('permission_name')->from('roles_permissions')
				->where('role_id', '=', $id)
				->execute($this->db)
				->as_array(NULL, 'permission_name');
	}

	protected function updatePermissions($role_id, $permissions)
	{
		$current_permissions = $this->getPermissions($role_id);

		$insert_query = DB::insert('roles_permissions', ['role_id', 'permission_name']);

		$new_permissions = array_diff($permissions, $current_permissions);

		foreach($new_permissions as $permission)
		{
			$insert_query->values([$role_id, $permission]);
		}

		if ($new_permissions) {
			$insert_query->execute($this->db);
		}

		// Remove permissions that are no longer needed
		$discarded_permissions = array_diff($current_permissions, $permissions);

		if ($discarded_permissions) {
			DB::delete('roles_permissions')
				->where('permission_name', 'IN', $discarded_permissions)
				->where('role_id', '=', $role_id)
				->execute($this->db);
		}
	}

	// Ushahidi_Repository
	public function getEntity(Array $data = null)
	{
		if (!empty($data['id']))
		{
			$data += [
				'permissions' => $this->getPermissions($data['id'])
			];
		}

		return new Role($data);
	}

	// SearchRepository
	public function getSearchFields()
	{
		return ['q', /* LIKE name */];
	}

	// RoleRepository
	public function doRolesExist(Array $roles = null)
	{
		if (!$roles)
		{
			// 0 === 0, all day every day
			return true;
		}

		$found = (int) $this->selectCount(['name' => $roles]);
		return count($roles) === $found;
	}


	// UpdateRepository
	public function create(Entity $entity)
	{
		$role = $entity->asArray();

		// Remove permissions
		unset($role['permissions']);

		// Create role
		$id = $this->executeInsert($this->removeNullValues($role));

		if ($entity->permissions) {
			$this->updatePermissions($id, $entity->permissions);
		}

		return $id;
	}

	// UpdateRepository
	public function update(Entity $entity)
	{
		$role = $entity->getChanged();

		// Remove permissions
		unset($role['permissions']);

		// Update the post
		$count = $this->executeUpdate(['id' => $entity->id], $role);

		// Update permissions
		if ($entity->hasChanged('permissions')) {
			$this->updatePermissions($entity->id, $entity->permissions);
		}

		return $count;
	}

	// SearchRepository
	public function setSearchConditions(SearchData $search)
	{
		$query = $this->search_query;

		if ($search->q)
		{
			$query->where('name', 'LIKE', "%" .$search->q ."%");
		}

		return $query;
	}


	// Ushahidi_Repository
	public function exists($role = '')
	{
		if (!$role) { return false; }
		return (bool) $this->selectCount(['name' => $role]);
	}

	// RoleRepository
	public function getByName($name)
	{
		return $this->getEntity($this->selectOne(compact('name')));
	}
}
