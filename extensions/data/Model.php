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
	 * // Add this filter at end of model file(s)
	 * Posts::applyFilter('save', \li3_save_all\extensions\data\Model::save_filter());
	 * 
	 * // In controller:
	 * $post->save($this->request->data, array('with' => array('Author')));
	 * }}}
	 *
	 * @return closure
	 */
	public static function save_filter() {
		return function($self, $params, $chain) {
			list($entity, $data, $options) = array($params['entity'], $params['data'], $params['options']);

			// Return home early, we don't need anything else from this class.
			if (empty($options['with'])) {
				return $chain->next($self, $params, $chain);
			}

			if (is_string($options['with'])) {
				$options['with'] = array($options['with']);
			}

			// Model options
			$model = $entity->model();
			$name = $model::meta('name');
			$fields = array_keys($model::schema());

			// Don't want to reset entity data entirely if $data is null
			$data = (!$data) ? $entity->data() : $data;

			// Strip related model data from $entity
			$local = array();
			foreach ($fields as $field) {
				if (isset($data[$field])) {
					$local[$field] = $data[$field];
				}
			}
			$entity->set($local);

			// Validate inital model 
			$success = $entity->validates();
			$with = array();
			foreach ($options['with'] as $related) {
				if (isset($data[$related])) {
					$with[$related] = array();
					$relatedModel = '\\' . $model::relations($related)->to();
					$keys = $model::relations($related)->keys();
					$fk = current($keys);
					$pk = key($keys);
					if ($model::relations($related)->type() == 'hasMany') {
						foreach ($data[$related] as $k => $relatedData) {
							$local = array();
							foreach ($relatedData as $field => $value) {
								$local[$field] = $value;
							}

							$with[$related][$k] = $relatedModel::create();
							$with[$related][$k]->set($local);
							$success = $with[$related][$k]->validates() && $success;
						}
						// see Model::bind();
						$entity->$related = new \lithium\data\collection\RecordSet(array('data' => $with[$related]));
					}
				}
			}

			if (!$success) {
				$entity->errors($model::errors($entity));
				return false;
			}
			$result = $chain->next($self, $params, $chain);

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
		};
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
