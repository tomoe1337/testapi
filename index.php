<?php

$webhookUrl = 'https://b24-jr3k5i.bitrix24.ru/rest/1/m8plllckhgjsn4w0/';

// Функция для отправки запросов к REST API Битрикс24
function sendRequest($method, $params = []) {
    global $webhookUrl;

    $url = $webhookUrl . $method;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

//количество контактов с заполненным полем COMMENTS
function getContactsWithComments() {
    $params = [
        'select' => ['ID','COMMENTS']
    ];
    $response = sendRequest('crm.contact.list', $params)['result'];


    // Фильтруем контакты с заполненным COMMENTS (не null и не пустая строка)
    $contactsWithComments = array_filter($response , function($resp) {
        return isset($resp['COMMENTS']) && trim($resp['COMMENTS']) != '';
    });

    return count($contactsWithComments);
}

// все сделки без контактов
function getDealsWithoutContacts() {
    $params = [
        'select' => ['ID', 'TITLE','CONTACT_ID']
    ];

    $response = sendRequest('crm.deal.list', $params)['result'];

    $dealsWithoutContacts = array_filter($response , function($resp) {
        return !isset($resp['CONTACT_ID']);
    });
    return $dealsWithoutContacts;
}

// количество сделок в каждой из существующих Направлений
function getDealsByDirections() {
    $categoriesResponse = sendRequest('crm.category.list?entityTypeId=1038');
    $categories = $categoriesResponse['result'];

    $dealsByDirection = [];
    foreach ($categories['categories'] as $category) {
        $categoryId = $category['id'];
        $params = [
            'filter' => ['CATEGORY_ID' => $categoryId],
            'select' => ['ID']
        ];
        $dealsResponse = sendRequest('crm.deal.list', $params);
        $dealsByDirection[$categoryId] = count($dealsResponse['result']);
    }

    return $dealsByDirection;
}

// Поиск кода баллов
function getSumPointsFromSmartProcess() {
    // Узнаем код поля "Баллы"
    $fieldsResponse = sendRequest('crm.item.fields', ['entityTypeId' => 1038]);
    $fields = $fieldsResponse['result'];

    $pointsFieldCode = null;


    foreach ($fields['fields'] as $code => $field ) {
        if ($field['title'] === 'Баллы') {
            $pointsFieldCode = $code;
            break;
        }
    }


    if (!$pointsFieldCode) {
        return "Поле 'Баллы' не найдено!";
    }

    //Получаем элементы Смарт процесса и считаем сумму
    $params = [
        'entityTypeId' => 1038,
        'select' => [$pointsFieldCode]
    ];
    $itemsResponse = sendRequest('crm.item.list', $params);

    $sum = 0;
    foreach ($itemsResponse['result']['items'] as $item) {

        $sum += (int)$item[$pointsFieldCode];
    }

    return $sum;
}

$result = [];



$deals_list = sendRequest('crm.deal.list')['result'];
$result["count_deals"] =  count($deals_list);


$contact_list = sendRequest('crm.contact.list')['result'];
$result["count_contact"] = count($contact_list);


$result["count_with_comments"] = getContactsWithComments();

//Кол-во сделок без контактов
$dealsWithoutContacts = getDealsWithoutContacts();
$result["count_no_contact"] = count($dealsWithoutContacts);

/*Предпологаю что есть сделки без контактов потому что какие-то клиенты 
не оставили контактные данные либо это тестовые записи.Возможно контакты были удалены из базы, из другой связанной таблицы
*/

$dealsByDirections = getDealsByDirections();

foreach ($dealsByDirections as $directionId => $count) {
    $result['all_directionsId'] = $directionId;
    $result['count_on_directions'] = $count;
}

$result['points_sum'] = getSumPointsFromSmartProcess();

echo "<pre>";
print_r ($result);
echo "</pre>";