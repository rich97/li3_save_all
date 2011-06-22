<?php
/**
 * @package li3_save_all
 */

namespace li3_save_all\extensions\data;

/**
 * Entity to use with li3_save_all model for reporting nested validation errors
 */
class Entity extends \lithium\data\Entity {

	/**
	 * Ask for or set validation errors
	 *
	 * Added functionality in this overwriting is for reporting nested
	 * validation errors
	 *
	 * @param mixed $field
	 * @param mixed $value
	 * @return array
	 */
	public function errors($field = null, $value = null) {
		if (is_string($field) && $point = strpos($field, '.')) {
			$model = substr($field, 0, $point);
			if ($this->$model instanceof \lithium\data\Collection) {
				list(, $key, $field) = explode('.', $field);
				$related = $this->$model;
				$relatedModel = $related[$key];
				return $relatedModel->errors($field);
			} elseif ($this->$model instanceof \lithium\data\Entity) {
				list(, $field) = explode('.', $field);
				$relatedModel = $this->$model;
				return $relatedModel->errors($field);
			}
		}

		return parent::errors($field, $value);
	}

}

?>
