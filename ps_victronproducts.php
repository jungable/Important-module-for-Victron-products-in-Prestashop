<?php
/**
 * Module d'importation des produits et catégories Victron Energy pour PrestaShop
 * Version 3.8.0 : Externalisation du dictionnaire de traduction.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_VictronProducts extends Module
{
    private $manufacturerId = null;
    private $victronParentCategoryId = null;
    private $defaultLanguageId;
    private $lastError = null;
    private $productPrefix = 'VIC-';
    private $dictionary = [];

    public function __construct()
    {
        $this->name = 'ps_victronproducts';
        $this->tab = 'migration_tools';
        $this->version = '3.8.0';
        $this->author = "Vitasolar's IT development department";
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Importateur de produits Victron');
        $this->description = $this->l('Importe, met à jour et affiche les produits Victron Energy avec une arborescence de catégories et des images. Les noms de produits sont basés sur la description de l\'API Victron.');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->defaultLanguageId = (int)Configuration::get('PS_LANG_DEFAULT');
        
        $this->loadDictionary();
    }

    /**
     * Charge le dictionnaire de traduction depuis un fichier externe.
     */
    private function loadDictionary()
    {
        $dictionaryFile = __DIR__ . '/translations/fr.php';
        if (file_exists($dictionaryFile) && is_readable($dictionaryFile)) {
            $this->dictionary = include($dictionaryFile);
        }
    }

    public function install()
    {
        // Créer le dossier de traductions s'il n'existe pas
        if (!is_dir($this->local_path . 'translations')) {
            mkdir($this->local_path . 'translations', 0755, true);
        }

        return parent::install() &&
            Configuration::updateValue('VICTRON_API_KEY', '') &&
            Configuration::updateValue('VICTRON_PRICE_COEFFICIENT', '1.0') &&
            Configuration::updateValue('VICTRON_LAST_SYNC', 0) &&
            Configuration::updateValue('VICTRON_SECURE_KEY', md5(uniqid(rand(), true))) &&
            $this->registerHook('displayHome');
    }

    public function uninstall()
    {
        Configuration::deleteByName('VICTRON_API_KEY');
        Configuration::deleteByName('VICTRON_PRICE_COEFFICIENT');
        Configuration::deleteByName('VICTRON_LAST_SYNC');
        Configuration::deleteByName('VICTRON_PARENT_CATEGORY');
        Configuration::deleteByName('VICTRON_SECURE_KEY');
        return parent::uninstall();
    }

    /**
     * Traduit un texte en utilisant le dictionnaire chargé.
     * @param string $text Le texte à traduire.
     * @return string Le texte traduit.
     */
    private function translate($text)
    {
        if (empty($this->dictionary) || !is_string($text)) {
            return $text;
        }
        // Remplacement insensible à la casse
        return str_ireplace(array_keys($this->dictionary), array_values($this->dictionary), $text);
    }

    public function getContent()
    {
        $output = '';

        if (!file_exists(__DIR__ . '/cacert.pem')) {
            $output .= $this->displayWarning($this->l('Le fichier cacert.pem est manquant. La connexion à l\'API et le téléchargement des images pourraient échouer.'));
        }
        
        if (!file_exists(__DIR__ . '/translations/fr.php')) {
            $output .= $this->displayWarning($this->l('Le fichier de traduction translations/fr.php est manquant. Les traductions automatiques ne fonctionneront pas.'));
        }

        if (Tools::isSubmit('submitConfig')) {
            Configuration::updateValue('VICTRON_API_KEY', Tools::getValue('VICTRON_API_KEY'));
            Configuration::updateValue('VICTRON_PRICE_COEFFICIENT', (float)Tools::getValue('VICTRON_PRICE_COEFFICIENT'));
            $output .= $this->displayConfirmation($this->l('Paramètres mis à jour avec succès.'));
        } elseif (Tools::isSubmit('runSync')) {
            @set_time_limit(3000);
            @ini_set('memory_limit', '1024M');
            $result = $this->runSync();
            if ($result) {
                $output .= $this->displayConfirmation($this->l('Synchronisation terminée avec succès.'));
            } else {
                $output .= $this->displayError($this->l('La synchronisation a échoué : ') . $this->lastError);
            }
        } elseif (Tools::isSubmit('clearProducts')) {
            $deletedCount = $this->clearVictronProducts();
            if ($deletedCount !== false) {
                 $output .= $this->displayConfirmation(sprintf($this->l('%d produits Victron ont été supprimés avec succès.'), $deletedCount));
            } else {
                $output .= $this->displayError($this->l('Une erreur est survenue lors de la suppression des produits.'));
            }
        }

        // Generate Secure Key if missing (legacy update support)
        if (!Configuration::get('VICTRON_SECURE_KEY')) {
            Configuration::updateValue('VICTRON_SECURE_KEY', md5(uniqid(rand(), true)));
        }
        $secureKey = Configuration::get('VICTRON_SECURE_KEY');
        $cronUrl = $this->context->shop->getBaseURL() . 'modules/' . $this->name . '/cron.php?token=' . $secureKey;

        $output .= $this->displayInformation(
            $this->l('URL pour la tâche CRON :') . '<br/>' .
            '<strong><a href="' . $cronUrl . '" target="_blank">' . $cronUrl . '</a></strong>'
        );

        $lastSync = (int)Configuration::get('VICTRON_LAST_SYNC');
        if (!$lastSync) {
            $output .= $this->displayWarning($this->l('Aucune synchronisation n\'a encore été effectuée.'));
        } else {
            $output .= $this->displayInformation(sprintf($this->l('Dernière synchronisation effectuée le : %s'), date('d/m/Y H:i:s', $lastSync)));
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => ['title' => $this->l('Configuration de l\'API Victron'), 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Clé API Victron E-Order'),
                        'name' => 'VICTRON_API_KEY',
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Coefficient de prix'),
                        'name' => 'VICTRON_PRICE_COEFFICIENT',
                        'desc' => $this->l('Le prix d\'achat sera multiplié par ce coefficient pour obtenir le prix de vente.'),
                        'required' => true
                    ]
                ],
                'submit' => ['title' => $this->l('Enregistrer'), 'name' => 'submitConfig'],
                'buttons' => [
                    ['title' => $this->l('Lancer la Synchronisation Complète'), 'name' => 'runSync', 'type' => 'submit', 'class' => 'btn btn-primary pull-right', 'icon' => 'process-icon-refresh'],
                    ['title' => $this->l('Nettoyer les produits Victron'), 'name' => 'clearProducts', 'type' => 'submit', 'class' => 'btn btn-danger', 'icon' => 'process-icon-delete', 'confirm' => $this->l('Êtes-vous sûr de vouloir supprimer tous les produits dont la référence commence par "VIC-" ? Cette action est irréversible.')]
                ]
            ]
        ];
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value['VICTRON_API_KEY'] = Configuration::get('VICTRON_API_KEY');
        $helper->fields_value['VICTRON_PRICE_COEFFICIENT'] = Configuration::get('VICTRON_PRICE_COEFFICIENT');
        return $helper->generateForm([$fields_form]);
    }
    
    private function fetchApiData($endpoint, $apiKey)
    {
        $baseUrl = 'https://eorder.victronenergy.com';
        $allData = [];
        $nextUrl = $baseUrl . $endpoint . '?format=json&language=fr_FR';

        while ($nextUrl) {
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $nextUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYPEER => file_exists(__DIR__ . '/cacert.pem'),
                CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey, 'Accept: application/json'],
                CURLOPT_USERAGENT => 'PrestaShop-Module/1.0',
            ];
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            
            if ($response === false) {
                $this->lastError = "Erreur cURL : " . curl_error($ch);
                curl_close($ch);
                return false;
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $this->lastError = "Erreur HTTP : $httpCode.";
                return false;
            }

            $data = json_decode($response, true);
            if (isset($data['results']) && is_array($data['results'])) {
                $allData = array_merge($allData, $data['results']);
                $nextUrl = $data['next'] ?? null;
            } else {
                $allData = is_array($data) ? $data : [];
                $nextUrl = null;
            }
        }
        return $allData;
    }

    private function setupEnvironment()
    {
        $this->manufacturerId = Manufacturer::getIdByName('Victron Energy');
        if (!$this->manufacturerId) {
            $manufacturer = new Manufacturer();
            $manufacturer->name = 'Victron Energy';
            if ($manufacturer->add()) {
                $this->manufacturerId = $manufacturer->id;
            }
        }

        $this->victronParentCategoryId = (int)Configuration::get('VICTRON_PARENT_CATEGORY');
        if (!$this->victronParentCategoryId || !Validate::isLoadedObject(new Category($this->victronParentCategoryId))) {
            $category = new Category();
            $category->name = array_fill_keys(Language::getIDs(true), 'Produits Victron Energy');
            $category->link_rewrite = array_fill_keys(Language::getIDs(true), Tools::str2url('produits-victron-energy'));
            $category->id_parent = (int)Configuration::get('PS_HOME_CATEGORY');
            $category->active = true;
            if ($category->add()) {
                $this->victronParentCategoryId = $category->id;
                Configuration::updateValue('VICTRON_PARENT_CATEGORY', $this->victronParentCategoryId);
            } else {
                $this->lastError = $this->l('Impossible de créer la catégorie parente Victron.');
                return false;
            }
        }
        return true;
    }

    public function runSync()
    {
        if (!$this->setupEnvironment()) return false;

        $apiKey = Configuration::get('VICTRON_API_KEY');
        if (empty($apiKey)) {
            $this->lastError = $this->l('La clé API n\'est pas configurée.');
            return false;
        }
        
        $productsData = $this->fetchApiData('/api/v1/products-extended/', $apiKey);
        $apiCategories = $this->fetchApiData('/api/v1/categories/', $apiKey);
        
        if ($productsData === false || $apiCategories === false) {
            return false;
        }
        
        $categoriesMap = $this->processCategoryTree($productsData, $apiCategories);
        if ($categoriesMap === false) {
            $this->lastError = $this->l('Échec de la création de la structure des catégories.');
            return false;
        }

        $seenSkus = [];
        foreach ($productsData as $productData) {
            $sku = $this->importProduct($productData, $categoriesMap);
            if ($sku) {
                $seenSkus[] = $sku;
            }
        }
        
        $this->pruneProducts($seenSkus);
        
        Configuration::updateValue('VICTRON_LAST_SYNC', time());
        return true;
    }
    
    private function pruneProducts(array $seenSkus)
    {
        if (empty($seenSkus)) return;

        // Get all products with our prefix
        $sql = 'SELECT p.id_product, p.reference 
                FROM `'._DB_PREFIX_.'product` p 
                WHERE p.reference LIKE "'.pSQL($this->productPrefix).'%"';
        
        $results = Db::getInstance()->executeS($sql);
        
        if (!$results) return;
        
        foreach ($results as $row) {
            // If the product DB reference is NOT in the list of SKUs we just processed
            if (!in_array($row['reference'], $seenSkus)) {
                $product = new Product((int)$row['id_product']);
                if (Validate::isLoadedObject($product)) {
                    $product->delete();
                }
            }
        }
    }

    private function processCategoryTree(array $productsData, array $apiCategories)
    {
        $categoryImageMap = [];
        foreach ($apiCategories as $categoryData) {
            if (!empty($categoryData['name']) && !empty($categoryData['image'])) {
                $categoryImageMap[trim($categoryData['name'])] = $categoryData['image'];
            }
        }

        $fallbackImageMap = [];
        foreach ($productsData as $product) {
            $group = !empty($product['category']) ? trim($product['category']) : 'Catégorie non définie';
            if (!isset($fallbackImageMap[$group]) && !empty($product['product_data']['image'])) {
                $fallbackImageMap[$group] = $product['product_data']['image'];
            }
        }

        $tree = [];
        foreach ($productsData as $product) {
            $group = !empty($product['category']) ? trim($product['category']) : 'Catégorie non définie';
            $subGroup = !empty($product['subcategory']) ? trim($product['subcategory']) : 'Sous-catégorie non définie';
            
            if (!isset($tree[$group])) {
                $tree[$group] = [];
            }
            if (!in_array($subGroup, $tree[$group])) {
                $tree[$group][] = $subGroup;
            }
        }

        $idMap = [];
        foreach ($tree as $groupName => $subGroups) {
            $translatedGroupName = $this->translate($groupName);
            $id_group = (int)Db::getInstance()->getValue('
                SELECT c.id_category FROM `'._DB_PREFIX_.'category` c
                LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.id_category = cl.id_category AND cl.id_lang = '.(int)$this->defaultLanguageId.')
                WHERE cl.name = "'.pSQL($translatedGroupName).'" AND c.id_parent = '.(int)$this->victronParentCategoryId
            );

            if (!$id_group) {
                $category = new Category();
                $category->name = array_fill_keys(Language::getIDs(true), $translatedGroupName);
                $category->link_rewrite = array_fill_keys(Language::getIDs(true), Tools::str2url($translatedGroupName));
                $category->id_parent = $this->victronParentCategoryId;
                $category->active = true;
                if (!$category->add()) {
                    continue;
                }
                $id_group = $category->id;
            }
            
            $imageUrl = $categoryImageMap[$groupName] ?? ($fallbackImageMap[$groupName] ?? null);
            if ($id_group && $imageUrl) {
                $this->importCategoryImage($id_group, $imageUrl);
            }
            
            $idMap[$groupName] = ['id' => $id_group, 'subgroups' => []];

            foreach ($subGroups as $subGroupName) {
                $translatedSubGroupName = $this->translate($subGroupName);
                $id_subgroup = (int)Db::getInstance()->getValue('
                    SELECT c.id_category FROM `'._DB_PREFIX_.'category` c
                    LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.id_category = cl.id_category AND cl.id_lang = '.(int)$this->defaultLanguageId.')
                    WHERE cl.name = "'.pSQL($translatedSubGroupName).'" AND c.id_parent = '.(int)$id_group
                );
                
                if (!$id_subgroup) {
                    $subCategory = new Category();
                    $subCategory->name = array_fill_keys(Language::getIDs(true), $translatedSubGroupName);
                    $subCategory->link_rewrite = array_fill_keys(Language::getIDs(true), Tools::str2url($translatedSubGroupName));
                    $subCategory->id_parent = $id_group;
                    $subCategory->active = true;
                    if (!$subCategory->add()) {
                        continue;
                    }
                    $id_subgroup = $subCategory->id;
                }
                $idMap[$groupName]['subgroups'][$subGroupName] = $id_subgroup;
            }
        }
        return $idMap;
    }

    private function importProduct($productData, $categoriesMap)
    {
        if (empty($productData['sku'])) return;
        $reference = $this->productPrefix . $productData['sku'];

        $productId = (int)Product::getIdByReference($reference);
        $product = new Product($productId ?: null);
        
        $needsSave = false;
        
        // --- Core Fields Logic ---
        $coefficient = (float)Configuration::get('VICTRON_PRICE_COEFFICIENT');
        if ($coefficient <= 0) $coefficient = 1.0;
        $newPrice = (float)($productData['price'] ?? 0) * $coefficient;
        
        if (abs($product->price - $newPrice) > 0.000001) {
            $product->price = $newPrice;
            $needsSave = true;
        }

        if ($product->id_manufacturer != $this->manufacturerId) {
            $product->id_manufacturer = $this->manufacturerId;
            $needsSave = true;
        }

        if ($product->reference !== $reference) {
            $product->reference = $reference;
            $needsSave = true;
        }
        
        if (!$product->active) { $product->active = true; $needsSave = true; }
        if ($product->state != 1) { $product->state = 1; $needsSave = true; }

        $groupName = !empty($productData['category']) ? trim($productData['category']) : $this->l('Catégorie non définie');
        $subGroupName = !empty($productData['subcategory']) ? trim($productData['subcategory']) : $this->l('Sous-catégorie non définie');
        $id_group = $categoriesMap[$groupName]['id'] ?? $this->victronParentCategoryId;
        $id_subgroup = $categoriesMap[$groupName]['subgroups'][$subGroupName] ?? $id_group;
        
        if ($product->id_category_default != $id_subgroup) {
            $product->id_category_default = $id_subgroup;
            $needsSave = true;
        }

        $newWeight = (float)($productData['gross_weight'] ?? 0);
        $newWidth = isset($productData['carton_width_mm']) ? (float)$productData['carton_width_mm'] / 10 : 0;
        $newHeight = isset($productData['carton_height_mm']) ? (float)$productData['carton_height_mm'] / 10 : 0;
        $newDepth = isset($productData['carton_length_mm']) ? (float)$productData['carton_length_mm'] / 10 : 0;

        if (abs($product->weight - $newWeight) > 0.001) { $product->weight = $newWeight; $needsSave = true; }
        if (abs($product->width - $newWidth) > 0.001) { $product->width = $newWidth; $needsSave = true; }
        if (abs($product->height - $newHeight) > 0.001) { $product->height = $newHeight; $needsSave = true; }
        if (abs($product->depth - $newDepth) > 0.001) { $product->depth = $newDepth; $needsSave = true; }

        foreach (Language::getIDs(true) as $id_lang) {
            $original_name = $productData['description'] ?? $this->l('Produit Victron sans nom');
            $newName = $this->translate($original_name);
            
            if (!isset($product->name[$id_lang]) || $product->name[$id_lang] !== $newName) {
                $product->name[$id_lang] = $newName;
                if (empty($product->link_rewrite[$id_lang])) {
                    $product->link_rewrite[$id_lang] = Tools::str2url($newName);
                }
                $needsSave = true;
            }

            $original_description = $productData['product_data']['description'] ?? '';
            $newDesc = $this->translate($original_description);
            if (!isset($product->description[$id_lang]) || $product->description[$id_lang] !== $newDesc) {
                $product->description[$id_lang] = $newDesc;
                $needsSave = true;
            }
            
            $newShortDesc = Tools::truncateString(strip_tags($newDesc), 400);
            if (!isset($product->description_short[$id_lang]) || $product->description_short[$id_lang] !== $newShortDesc) {
                $product->description_short[$id_lang] = $newShortDesc;
                $needsSave = true;
            }
        }

        try {
            if ($needsSave || !$product->id) {
                $product->save();
                $product->updateCategories(array_unique([$this->victronParentCategoryId, $id_group, $id_subgroup]));
            }

            StockAvailable::setQuantity($product->id, 0, (int)($productData['stock_quantity'] ?? 100));

            if (!Image::hasImages($this->defaultLanguageId, $product->id) && !empty($productData['product_data']['image'])) {
                $this->importProductImage($product, $productData['product_data']['image']);
            }

            $cliFeatures = [];
            $cliFeatures[$this->l('Pays d\'origine')] = $productData['country_of_origin'] ?? '';
            $cliFeatures[$this->l('Poids net')] = ($productData['net_weight'] ?? '') . ' kg';
            $cliFeatures[$this->l('Quantité par palette')] = $productData['quantity_per_pallet'] ?? '';
            $cliFeatures[$this->l('Quantité minimale de commande')] = $productData['minimum_order_quantity'] ?? '';
            $cliFeatures[$this->l('Tension')] = $productData['voltage'] ?? '';

            if (isset($productData['product_data']['pms_technical_data']) && is_array($productData['product_data']['pms_technical_data'])) {
                foreach ($productData['product_data']['pms_technical_data'] as $techData) {
                    if (!empty($techData['field_name']) && !empty($techData['field_value'])) {
                        $translatedName = $this->translate($techData['field_name']);
                        $translatedValue = $this->translate($techData['field_value']);
                        $cliFeatures[$translatedName] = $translatedValue;
                    }
                }
            }
            
            $this->syncProductFeatures($product->id, $cliFeatures);
            
            return $reference; 

        } catch (Exception $e) {
            $this->lastError = $this->l('Erreur produit') . ' ' . $reference . ': ' . $e->getMessage();
            return null;
        }
    }

    private function syncProductFeatures($productId, $newFeatures)
    {
        foreach ($newFeatures as $name => $value) {
            $this->addProductFeature($productId, $name, $value);
        }
    }
    
    private function addProductFeature($productId, $featureName, $featureValue)
    {
        if (empty($featureValue)) return;

        $id_feature = (int)Db::getInstance()->getValue('
            SELECT id_feature FROM `' . _DB_PREFIX_ . 'feature_lang` 
            WHERE name = \'' . pSQL($featureName) . '\' AND id_lang = ' . (int)$this->defaultLanguageId
        );

        if (!$id_feature) {
            $feature = new Feature();
            $feature->name = array_fill_keys(Language::getIDs(true), $featureName);
            $feature->add();
            $id_feature = $feature->id;
        }

        $id_feature_value = (int)Db::getInstance()->getValue('
            SELECT fv.id_feature_value FROM `' . _DB_PREFIX_ . 'feature_value` fv
            LEFT JOIN `' . _DB_PREFIX_ . 'feature_value_lang` fvl ON fv.id_feature_value = fvl.id_feature_value
            WHERE fvl.value = \'' . pSQL($featureValue) . '\' 
            AND fv.id_feature = ' . (int)$id_feature . ' 
            AND fvl.id_lang = ' . (int)$this->defaultLanguageId . '
            AND fv.custom = 0'
        );

        if (!$id_feature_value) {
            $featureValueObj = new FeatureValue();
            $featureValueObj->id_feature = $id_feature;
            $featureValueObj->value = array_fill_keys(Language::getIDs(true), $featureValue);
            $featureValueObj->custom = 0; 
            $featureValueObj->add();
            $id_feature_value = $featureValueObj->id;
        }

        $isAssigned = (bool)Db::getInstance()->getValue('
            SELECT id_feature_value FROM `' . _DB_PREFIX_ . 'feature_product`
            WHERE id_product = ' . (int)$productId . ' 
            AND id_feature = ' . (int)$id_feature . '
            AND id_feature_value = ' . (int)$id_feature_value
        );

        if (!$isAssigned) {
            Db::getInstance()->execute('
                DELETE FROM `' . _DB_PREFIX_ . 'feature_product`
                WHERE id_product = '.(int)$productId.' AND id_feature = '.(int)$id_feature
            );
            Product::addFeatureProductImport($productId, $id_feature, $id_feature_value);
        }
    }
    
    private function importProductImage($product, $imageUrl)
    {
        $image = new Image();
        $image->id_product = (int)$product->id;
        $image->position = Image::getHighestPosition($product->id) + 1;
        $image->cover = true;
        
        if ($image->add()) {
            $this->copyImg($product->id, $image->id, $imageUrl, 'products');
        }
    }

    private function importCategoryImage($categoryId, $imageUrl)
    {
        $category = new Category($categoryId);
        if (Validate::isLoadedObject($category) && !$category->id_image) {
            $this->copyImg($categoryId, null, $imageUrl, 'categories');
        }
    }

    protected function copyImg($id_entity, $id_image = null, $url, $entity = 'products')
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        if (!$tmpfile) {
            return;
        }

        $urlParts = explode('/', $url);
        $fileName = array_pop($urlParts);
        $encodedFileName = rawurlencode($fileName);
        $encodedUrl = implode('/', $urlParts) . '/' . $encodedFileName;

        if (Tools::copy($encodedUrl, $tmpfile)) {
            if ($entity === 'categories') {
                $path = _PS_CAT_IMG_DIR_ . (int)$id_entity;
                $original_image_path = $path . '.jpg';
                
                ImageManager::resize($tmpfile, $original_image_path, null, null, 'jpg', false);

                $types = ImageType::getImagesTypes('categories');
                foreach ($types as $image_type) {
                    ImageManager::resize(
                        $original_image_path,
                        $path . '-' . stripslashes($image_type['name']) . '.jpg',
                        $image_type['width'],
                        $image_type['height'],
                        'jpg'
                    );
                }
            } else { // products
                $path = (new Image($id_image))->getPathForCreation();
                ImageManager::resize($tmpfile, $path . '.jpg');
                $types = ImageType::getImagesTypes('products');
                foreach ($types as $image_type) {
                    ImageManager::resize($path . '.jpg', $path . '-' . stripslashes($image_type['name']) . '.jpg', $image_type['width'], $image_type['height']);
                }
            }
        }
        @unlink($tmpfile);
    }

    private function clearVictronProducts()
    {
        $productIds = Db::getInstance()->executeS(
            'SELECT id_product FROM `'._DB_PREFIX_.'product` WHERE reference LIKE "'.pSQL($this->productPrefix).'%"'
        );

        if (empty($productIds)) {
            return 0;
        }
        
        $count = 0;
        foreach ($productIds as $row) {
            $product = new Product((int)$row['id_product']);
            if (Validate::isLoadedObject($product) && $product->delete()) {
                $count++;
            }
        }
        return $count;
    }
    
    public function hookDisplayHome($params)
    {
        return;
    }
}
