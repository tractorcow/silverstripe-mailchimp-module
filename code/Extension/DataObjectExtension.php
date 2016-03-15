<?php

class DataObjectExtension extends DataExtension {

    /**
     * Overloads the Default DataObject::getManyManyComponents() Method In Order to Use a Custom ManyManyList::add() Method Which Includes onLink() and onUnlink() Hooks
	 * Returns a many-to-many component, as a MyManyManyList (which overloads the add() and remove() methods to add in some hooks)
	 * @param string $componentName Name of the many-many component
	 * @return MyManyManyList The set of components
	 *
	*/
	public function getMyManyManyComponents($componentName, $filter = "", $sort = "", $join = "", $limit = "") {

		list($parentClass, $componentClass, $parentField, $componentField, $table) = $this->owner->many_many($componentName);

		$result = Injector::inst()->create('MyManyManyList', $componentClass, $table, $componentField, $parentField, $this->owner->many_many_extraFields($componentName));

		if($this->owner->model) $result->setDataModel($this->owner->model);

		// If this is called on a singleton, then we return an 'orphaned relation' that can have the foreignID set elsewhere.
		$result = $result->forForeignID($this->owner->ID);

		return $result->where($filter)->sort($sort)->limit($limit);
	}

}