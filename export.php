<?php
	ini_set('max_execution_time', 0);
	$conn = new mysqli("localhost", "DB_Username", "DB_Password", "DB_Name");
	$products = [];
	$fields = [
		"P.*",
		"(SELECT Name FROM uc_brand WHERE ID=(SELECT BrandID FROM uc_product_brand WHERE ProductID=P.ID LIMIT 1)) as Brand",
		"(SELECT GROUP_CONCAT(FileName) FROM uc_product_image WHERE ProductID=P.ID GROUP BY ProductID) as Images",
		"(SELECT GROUP_CONCAT(Keyword) FROM uc_product_keyword WHERE ProductID=P.ID GROUP BY ProductID) as Keywords",
		"(SELECT GROUP_CONCAT(Name SEPARATOR '-SEP-') FROM uc_catalog WHERE ID IN (SELECT CatalogID FROM uc_product_catalog WHERE ProductID=P.ID)) as Catalogs",
		"(SELECT GROUP_CONCAT(Name SEPARATOR '-SEP-') FROM uc_listing WHERE ID IN (SELECT ListingID FROM uc_product_listing WHERE ProductID=P.ID)) as Listings"
	];
	$fields = implode(",", $fields);
	$q = $conn->query("SELECT {$fields} FROM uc_product P ORDER BY ID DESC");
	while($f = $q->fetch_object()){
		$products[$f->ID] = (object) [
			"ID" => $f->ID,
			"SKU" => $f->SKU,
			"name" => $f->Name,
			"brand" => $f->Brand,
			"description" => $f->ShortDescription,
			"browserTitle" => $f->BrowserTitle,
			"metaKeyword" => $f->MetaKeyword,
			"full_description" => htmlspecialchars_decode($f->Description),
			"keywords" => explode(",", $f->Keywords),
			"catalogs" => explode("-SEP-", $f->Catalogs),
			"listings" => explode("-SEP-", $f->Listings),
			"images" => explode(",", $f->Images),
			"variations" => get_variations($f->ID),
		];

		$products[$f->ID]->images = array_map(function($fileName) use ($f){
			return "product_image/{$f->ID}/{$fileName}";
		}, $products[$f->ID]->images);
	}
	function get_variations($id){
		global $conn;
		$response = [];
		$fields = [
			"O.SKU",
			"O.Price",
			"O.StrikePrice",
			"O.Weight",
			"A.AttributeValueID as IDS"
		];
		$fields = implode(",", $fields);
		$q = $conn->query("SELECT {$fields} FROM uc_product_option O INNER JOIN (SELECT GROUP_CONCAT(AttributeValueID) as AttributeValueID,OptionID FROM uc_product_attribute GROUP BY OptionID) as A ON OptionID = O.ID WHERE ProductID='{$id}'");
		while($f = $q->fetch_object()){
			$response[] = (object) [
				"SKU" => $f->SKU,
				"price" => $f->Price,
				"strikePrice" => $f->StrikePrice,
				"weight" => $f->Weight,
				"attributes" => get_attributes($f->IDS)
			];
		}
		return $response;
	}
	function get_attributes($ids){
		global $conn;
		$ids = explode(",", $ids);
		$ids = array_map("trim", $ids);
		$response = [];
		foreach($ids as $singleID){
			$q = $conn->query("SELECT B.Name as name, A.Name as value  FROM uc_attribute_value A LEFT JOIN uc_attribute B ON A.AttributeId=B.ID WHERE A.ID='{$singleID}'");
			$response[] = $q->fetch_object();
		}
		return $response;
	}

	file_put_contents(__DIR__."/full.json", json_encode($products));
