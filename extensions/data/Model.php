<?php

namspeace li3_save_all\extensions\data

class Model extends \lithium\data\Model {

	public function saveall(\lithium\data\Entity $entity, array $data, array $options = array()) {
		$options += array('with' => array('Users'));
		$model = $entity->model();
		$name = $model::meta('name');
		$fields = array_keys($model::schema());
		$local = array();
		foreach ($fields as $field) {
			if (isset($data[$field])) {
				$local[$field] = $data[$field];
			}
		}
		$entity->set($local);
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
						$success = $relatedEntities[$k]->validates() && $success ;
					}
					$entity->$related = new \lithium\data\collection\RecordSet(array('data' => $relatedEntities));
				}
			}
		}
		return $success;
	}

}

?>
