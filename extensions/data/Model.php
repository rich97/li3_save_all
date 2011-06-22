<?php

namespace li3_save_all\extensions\data;

class Model extends \lithium\data\Model {

	public function save($entity, $data, array $options = array()) {
		// Return home early, we don't need anything else from this class.
        if (empty($options['with'])) {
            // Remove this we don't want to save while testing the validation
            return false;
            return parent::save($entity, $data, $options);
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
		foreach ($options['with'] as $related) {
			if (isset($data[$related])) {
				$relatedModel = '\\' . $model::relations($related)->to();
				$fk = current($model::relations($related)->keys());
				if ($model::relations($related)->type() == 'hasMany') {
					$relatedEntities = array();
					foreach ($data[$related] as $k => $relatedData) {
						$local = array();
						foreach ($relatedData as $field => $value) {
							$local[$field] = $value;
						}
						$local[$fk] = $entity->id;

						$relatedEntities[$k] = $relatedModel::create();
						$relatedEntities[$k]->set($local);
						$success = $relatedEntities[$k]->validates() && $success;
					}
					// see Model::bind();
					$entity->$related = new \lithium\data\collection\RecordSet(array('data' => $relatedEntities));
				}
			}
		}
		return $success;
	}

}

?>
