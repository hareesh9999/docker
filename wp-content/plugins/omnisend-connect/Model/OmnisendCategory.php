<?php
if (!defined('ABSPATH')) {
    exit;
}

class OmnisendCategory
{

    /*Required*/
    public $categoryID;
    public $title;

    public static function create($id)
    {
        try {
            return new OmnisendCategory($id);
        } catch (OmnisendEmptyRequiredFieldsException $exception) {
            return null;
        }
    }

    private function __construct($id)
    {

        $this->categoryID = "" . $id;

        $term = get_term($id);
        if ($term) {
            $this->title = $term->name;
        }

        if (empty($this->categoryID) || empty($this->title)) {
            throw new OmnisendEmptyRequiredFieldsException();
        }
    }

}
