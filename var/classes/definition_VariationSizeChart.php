<?php

/**
 * Inheritance: no
 * Variants: no
 * Title: Ebat Şablonu
 * Ürün varyasyonlarının açılacağı ebatlara ilişkin şablon.
 *
 * Fields Summary:
 * - description [textarea]
 * - sizeOptions [table]
 */

return \Pimcore\Model\DataObject\ClassDefinition::__set_state(array(
   'dao' => NULL,
   'id' => 'variation_size_chart',
   'name' => 'VariationSizeChart',
   'title' => 'Ebat Şablonu',
   'description' => 'Ürün varyasyonlarının açılacağı ebatlara ilişkin şablon.',
   'creationDate' => NULL,
   'modificationDate' => 1750093177,
   'userOwner' => 2,
   'userModification' => 2,
   'parentClass' => '',
   'implementsInterfaces' => '',
   'listingParentClass' => '',
   'useTraits' => '',
   'listingUseTraits' => '',
   'encryption' => false,
   'encryptedTables' => 
  array (
  ),
   'allowInherit' => false,
   'allowVariants' => false,
   'showVariants' => false,
   'layoutDefinitions' => 
  \Pimcore\Model\DataObject\ClassDefinition\Layout\Panel::__set_state(array(
     'name' => 'pimcore_root',
     'type' => NULL,
     'region' => NULL,
     'title' => NULL,
     'width' => 0,
     'height' => 0,
     'collapsible' => false,
     'collapsed' => false,
     'bodyStyle' => NULL,
     'datatype' => 'layout',
     'children' => 
    array (
      0 => 
      \Pimcore\Model\DataObject\ClassDefinition\Layout\Panel::__set_state(array(
         'name' => 'Layout',
         'type' => NULL,
         'region' => NULL,
         'title' => '',
         'width' => '',
         'height' => '',
         'collapsible' => false,
         'collapsed' => false,
         'bodyStyle' => '',
         'datatype' => 'layout',
         'children' => 
        array (
          0 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Textarea::__set_state(array(
             'name' => 'description',
             'title' => 'Kullanım Amacı',
             'tooltip' => 'Bu ebat şablonu hangi ürünlerde kullanılmak üzere tasarlandı.',
             'mandatory' => true,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => '',
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' => 
            array (
            ),
             'maxLength' => NULL,
             'showCharCount' => false,
             'excludeFromSearchIndex' => false,
             'height' => '',
             'width' => '',
          )),
          1 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Table::__set_state(array(
             'name' => 'sizeOptions',
             'title' => 'Ebat Seçenekleri',
             'tooltip' => 'Bu şablonda izin verilen ebatları en, boy ve ağırlık değerleri ile birlikte giriniz.',
             'mandatory' => true,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => '',
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' => 
            array (
            ),
             'cols' => 4,
             'colsFixed' => true,
             'rows' => NULL,
             'rowsFixed' => false,
             'data' => 'S|0|0|0
M|0|0|0
L|0|0|0
XL|0|0|0
2XL|0|0|0
3XL|0|0|0',
             'columnConfigActivated' => true,
             'columnConfig' => 
            array (
              0 => 
              array (
                'key' => 0,
                'label' => 'Size',
              ),
              1 => 
              array (
                'key' => 1,
                'label' => 'En',
              ),
              2 => 
              array (
                'key' => 2,
                'label' => 'Boy',
              ),
              3 => 
              array (
                'key' => 3,
                'label' => 'Ağırlık',
              ),
            ),
             'height' => '',
             'width' => 320,
          )),
        ),
         'locked' => false,
         'blockedVarsForExport' => 
        array (
        ),
         'fieldtype' => 'panel',
         'layout' => NULL,
         'border' => false,
         'icon' => '',
         'labelWidth' => 100,
         'labelAlign' => 'left',
      )),
    ),
     'locked' => false,
     'blockedVarsForExport' => 
    array (
    ),
     'fieldtype' => 'panel',
     'layout' => NULL,
     'border' => false,
     'icon' => NULL,
     'labelWidth' => 100,
     'labelAlign' => 'left',
  )),
   'icon' => '',
   'group' => 'Variation Properties',
   'showAppLoggerTab' => false,
   'linkGeneratorReference' => '',
   'previewGeneratorReference' => '',
   'compositeIndices' => 
  array (
  ),
   'showFieldLookup' => false,
   'propertyVisibility' => 
  array (
    'grid' => 
    array (
      'id' => true,
      'key' => false,
      'path' => true,
      'published' => true,
      'modificationDate' => true,
      'creationDate' => true,
    ),
    'search' => 
    array (
      'id' => true,
      'key' => false,
      'path' => true,
      'published' => true,
      'modificationDate' => true,
      'creationDate' => true,
    ),
  ),
   'enableGridLocking' => false,
   'deletedDataComponents' => 
  array (
  ),
   'blockedVarsForExport' => 
  array (
  ),
   'fieldDefinitionsCache' => 
  array (
  ),
   'activeDispatchingEvents' => 
  array (
  ),
));
