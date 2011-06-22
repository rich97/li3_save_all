<?php
/**
 * @package li3_save_all
 */

namespace li3_save_all\extensions\data;

/**
 * Model extension for saving with related models:
 *
 *
 * @see \lithium\data\Model
 */
class Model extends \lithium\data\Model {

	/**
	 * Returns a filter closure that can be applied to models that needs a relationship save
	 * 
	 * Use:
	 * {{{
	 * // In controller:
	 * $post->save($this->request->data, array('with' => array('Author')));
	 * }}}
	 *
	 * @param \lihtuim\data\Entity $entity
	 * @param array $data
	 * @param array $options
	 * @return boolean
	 */
	public function save($entity, array $data = array(), array $options = array()) {
		// Return home early, we don't need anything else from this class.
		if (empty($options['with'])) {
			return parent::save($entity, $data, $options);
		}
		if (is_string($options['with'])) {
			$options['with'] = array($options['with']);
		}

		// Model options
		$model = $entity->model();

		// Don't want to reset entity data entirely if $data is null
		$data = (!$data) ? $entity->data() : $data;

		// Set fields ment for root model to it's entity
		$fields = array_keys($model::schema());
		$local = array();
		foreach ($fields as $field) {
			if (isset($data[$field])) {
				$local[$field] = $data[$field];
			}
		}
		$entity->set($local);

		$with = array();
		foreach ($options['with'] as $related) {
			if (isset($data[$related])) {
				$with[$related] = array();
				$relationship = $model::relations($related);
				$relatedModel = $relationship->to();
				switch ($relationship->type()) {
					case 'hasOne' :
					case 'belongsTo' :
						$entity->$related = $with[$related] = $relatedModel::create();
						$entity->$related->set($local);
						break;
					case 'hasMany' :
						foreach ($data[$related] as $k => $relatedData) {
							$local = array();
							foreach ($relatedData as $field => $value) {
								$local[$field] = $value;
							}

							$with[$related][$k] = $relatedModel::create();
							$with[$related][$k]->set($local);
						}
						$entity->$related = new \lithium\data\collection\RecordSet(array('data' => $with[$related]));
						break;
				}
			}
		}

		if (!$entity->validates()) {
			$entity->errors($model::errors($entity));
			return false;
		}
		$result = parent::save($entity, $data, $options);

		if (!$result) throw new \Exception ('Save on main failed. Save-all opperation halted.');

		foreach ($with as $related => $relatedEntities) {
			$keys = $model::relations($related)->keys();
			$fk = current($keys);
			$pk = key($keys);
			foreach ($relatedEntities as $relatedEntity) {
				$relatedEntity->$fk = $entity->$pk;
				if (!$relatedEntity->save()) 
						throw new \Exception ('Save on related failed. Save-all opperation halted.');
			}
		}
		return true;
	}

	/**
	 * Check related model for validation errors
	 *
	 * @param \lihtuim\data\Entity $entity
	 * @return boolean 
	 */
	public function validates($entity) {
		$success = parent::validates($entity);
		$model = $entity->model();
		foreach ($model::relations() as $related => $relationship) {
			if (isset($entity->$related)) {
				switch ($relationship->type()) {
					case 'hasOne' :
					case 'belongsTo' :
						$success = $entity->$related->validates() && $success;
						break;
					case 'hasMany' :
						foreach ($entity->$related as $relatedEntity) {
							$success = $relatedEntity->validates() && $success;
						}
						break;
				}
			}
		}
		return $success;
	}

	/**
	 * Get a combined, flat array of all validation errors
	 *
	 * @param \lihtuim\data\Entity $entity
	 * @return array
	 */
	public static function errors($entity) {
		$errors = $entity->errors();
		foreach (static::relations() as $related => $relationship) {

			if (!($entity->{$related}) || !($relErrors = $entity->{$related}->errors())) {
				continue;
			}
			switch ($relationship->type())  {
				case 'hasMany' :
					foreach ($relErrors as $k => $row) {
						foreach ($row as $field => $msgs) {
							$errors[$related . '.' . $k . '.' . $field] = $msgs;
						}
					}
					break;
				case 'belongsTo' :
				case 'hasOne' :
				default :
					foreach ($relErrors as $field => $msgs) {
						$errors[$related . '.' . $field] = $msgs;
					}
					break;
			}
		}
		return $errors;
	}

}

?>
