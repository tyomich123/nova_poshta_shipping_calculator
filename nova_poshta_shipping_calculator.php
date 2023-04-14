<?php
/**
 * Plugin Name: Nova Poshta Shipping Calculator
 * Description: Розрахунок вартості посилки через сервіс Нова Пошта за допомогою API.
 * Version: 1.0
 * Author: Артемко
 * License: GPL-2.0+
 */

function np_calculate_shipping_cost($city_sender, $city_recipient, $weight, $service_type, $cost, $cargo_type, $seats_amount, $redelivery_calculate = null, $pack_count = null, $pack_ref = null, $amount = null, $cargo_details = null) {
    $api_key = '74450b57b7f715b98cd7fbc46bd597bf';

    $request_data = [
        'apiKey' => $api_key,
        'modelName' => 'InternetDocument',
        'calledMethod' => 'getDocumentPrice',
        'methodProperties' => [
            'CitySender' => $city_sender,
            'CityRecipient' => $city_recipient,
            'Weight' => $weight,
            'ServiceType' => $service_type,
            'Cost' => $cost,
            'CargoType' => $cargo_type,
            'SeatsAmount' => $seats_amount
        ]
    ];

    if ($redelivery_calculate !== null) {
        $request_data['methodProperties']['RedeliveryCalculate'] = $redelivery_calculate;
    }

    if ($pack_count !== null) {
        $request_data['methodProperties']['PackCount'] = $pack_count;
    }

    if ($pack_ref !== null) {
        $request_data['methodProperties']['PackRef'] = $pack_ref;
    }

    if ($amount !== null) {
        $request_data['methodProperties']['Amount'] = $amount;
    }

    if ($cargo_details !== null) {
        $request_data['methodProperties']['CargoDetails'] = $cargo_details;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.novaposhta.ua/v2.0/json/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($request_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}

function calculate_shipping_cost_ajax_handler() {
    if (isset($_POST['sender_ref']) && isset($_POST['recipient_ref']) && isset($_POST['weight']) && isset($_POST['service_type']) && isset($_POST['cost']) && isset($_POST['cargo_type']) && isset($_POST['seats_amount'])) {
        $sender_ref = sanitize_text_field($_POST['sender_ref']);
        $recipient_ref = sanitize_text_field($_POST['recipient_ref']);
        $weight = floatval($_POST['weight']);
        $service_type = sanitize_text_field($_POST['service_type']);
        $cost = floatval($_POST['cost']);
        $cargo_type = sanitize_text_field($_POST['cargo_type']);
        $seats_amount = intval($_POST['seats_amount']);

        // Перевірка валідності значень
        if ($sender_ref === '' || $recipient_ref === '' || $weight <= 0 || $cost <= 0 || $seats_amount <= 0) {
            wp_send_json_error('Некоректні дані. Будь ласка, перевірте введені значення.');
            return;
        }

        // Виклик API для розрахунку вартості доставки
        $shipping_cost = np_calculate_shipping_cost($sender_ref, $recipient_ref, $weight, $service_type, $cost, $cargo_type, $seats_amount);

        if (isset($shipping_cost['success']) && $shipping_cost['success']) {
            wp_send_json_success($shipping_cost['data'][0]['Cost']);
        } else {
            wp_send_json_error('Не вдалося розрахувати вартість доставки. Спробуйте пізніше.');
        }
    } else {
        wp_send_json_error('Не всі дані введені. Будь ласка, перевірте введені значення.');
    }
}
add_action('wp_ajax_calculate_shipping_cost', 'calculate_shipping_cost_ajax_handler');
add_action('wp_ajax_nopriv_calculate_shipping_cost', 'calculate_shipping_cost_ajax_handler');

add_action('wp_ajax_calculate_shipping_cost', 'calculate_shipping_cost_ajax_handler');
add_action('wp_ajax_nopriv_calculate_shipping_cost', 'calculate_shipping_cost_ajax_handler');

function np_shipping_calculator_shortcode($atts) {
    ob_start();
    ?>
    <style>
    .input-wrapper {
        position: relative;
        margin-bottom: 10px;
    }

    #np-shipping-calculator-form {
        display: flex;
        flex-direction: column;
    }

    .input-wrapper input,
    .input-wrapper select {
        width: 100%;
    }

    .input-label {
        position: absolute;
        left: 5px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        transition: top 0.3s, font-size 0.3s;
    }

    .input-focused .input-label {
        top: 0;
        font-size: 0.8em;
    }
  }
</style>

<form id="np-shipping-calculator-form">
  <div class="input-wrapper">
    <label for="np-city-sender" class="input-label">Місто відправника:</label>
    <input type="text" id="np-city-sender" name="city_sender" list="np-city-sender-list" required>
    <datalist id="np-city-sender-list"></datalist>
  </div>

  <div class="input-wrapper">
    <label for="np-city-recipient" class="input-label">Місто отримувача:</label>
    <input type="text" id="np-city-recipient" name="city_recipient" list="np-city-recipient-list" required>
    <datalist id="np-city-recipient-list"></datalist>
  </div>

  <div class="input-wrapper">
    <label for="np-package-weight" class="input-label">Вага (кг):</label>
    <input type="number" id="package-weight" name="package-weight" step="0.1" min="0.1" required>
  </div>
    <div class="input-wrapper">
        <label for="service-type" class="input-label">Тип послуги:</label>
        <select id="service-type" name="service_type" required>
            <option value="WarehouseWarehouse">Склад-Склад</option>
            <option value="WarehouseDoors">Склад-Двері</option>
            <option value="DoorsWarehouse">Двері-Склад</option>
            <option value="DoorsDoors">Двері-Двері</option>
        </select>
    </div>
    <div class="input-wrapper">
    <label for="package-value" class="input-label">Оціночна вартість:</label>
    <input type="number" id="package-value" name="package-value" step="1" min="0" required>
</div>
    <div class="input-wrapper">
        <label for="cargo-type" class="input-label">Тип посилки:</label>
        <select id="cargo-type" name="cargo_type" required>
            <option value="Cargo">Вантажі</option>
            <option value="Cargo">Посилки</option>
            <option value="Documents">Документи</option>
            <option value="TiresWheels">Шини та диски</option>
            <option value="Pallet">Палети</option>
        </select>
    </div>
    <div class="input-wrapper">
        <label for="seats-amount" class="input-label">Кількість місць:</label>
        <input type="number" id="seats-amount" name="seats_amount" step="1" min="1" required>
    </div>
    <button id="np-calculate-shipping-cost" type="submit">Розрахувати</button>
    <input type="hidden" id="sender-ref" name="sender_ref">
    <input type="hidden" id="recipient-ref" name="recipient_ref">
</form>
<!-- Підключення jQuery -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<script>
// Функція, яка виконує запит до API та повертає список міст за частковим співпадінням
async function searchCities(partialCityName) {
    const apiKey = '74450b57b7f715b98cd7fbc46bd597bf'; // Замінити на власний ключ API Нової Пошти
    const url = `https://api.novaposhta.ua/v2.0/json/`;

    const request_data = {
        apiKey: apiKey,
        modelName: 'Address',
        calledMethod: 'searchSettlements',
        methodProperties: {
            CityName: partialCityName,
            Limit: 10
        }
    };

    try {
        const response = await fetch(url, {
            method: 'POST',
            body: JSON.stringify(request_data),
            headers: {
                'Content-Type': 'application/json'
            }
        });
        const data = await response.json();

        if (data.success === false) {
            throw new Error(`Не вдалося отримати список міст`);
        }

        return data.data[0].Addresses;
    } catch (error) {
        console.error(error);
        alert(`Помилка: ${error.message}`);
    }
}
// Функція для відображення списку міст під відповідним полем вводу
function displayCityList(cities, cityListElement) {
    cityListElement.innerHTML = '';
    cities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.Present;
        option.setAttribute('data-ref', city.Ref);
        cityListElement.appendChild(option);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const citySenderInput = document.getElementById('np-city-sender');
    const cityRecipientInput = document.getElementById('np-city-recipient');
    const citySenderList = document.getElementById('np-city-sender-list');
    const cityRecipientList = document.getElementById('np-city-recipient-list');
    });

citySenderInput.addEventListener('input', async () => {
    const partialCityName = citySenderInput.value;
    const cities = await searchCities(partialCityName);
    displayCityList(cities, citySenderList);
    const selectedOption = citySenderList.querySelector(`option[value="${citySenderInput.value}"]`);
    if (selectedOption) {
        document.getElementById('sender-ref').value = selectedOption.getAttribute('data-ref');
    } else {
        document.getElementById('sender-ref').value = '';
    }
});

cityRecipientInput.addEventListener('input', async () => {
    const partialCityName = cityRecipientInput.value;
    const cities = await searchCities(partialCityName);
    displayCityList(cities, cityRecipientList);
    const selectedOption = cityRecipientList.querySelector(`option[value="${cityRecipientInput.value}"]`);
    if (selectedOption) {
        document.getElementById('recipient-ref').value = selectedOption.getAttribute('data-ref');
    } else {
        document.getElementById('recipient-ref').value = '';
    }
});

// Функція для розрахунку вартості доставки
async function calculateShippingCost(formData) {
const apiKey = '74450b57b7f715b98cd7fbc46bd597bf'; // Замінити на власний ключ API Нової Пошти
const url = 'https://api.novaposhta.ua/v2.0/json/';
const request_data = {
apiKey: apiKey,
modelName: 'InternetDocument',
calledMethod: 'getDocumentPrice',
methodProperties: {
CitySender: formData.get('sender_ref'),
CityRecipient: formData.get('recipient_ref'),
Weight: formData.get('package-weight'),
ServiceType: formData.get('service_type'),
Cost: formData.get('package-value'),
CargoType: formData.get('cargo_type'),
SeatsAmount: formData.get('seats_amount')
}
};
try {
    const response = await fetch(url, {
        method: 'POST',
        body: JSON.stringify(request_data),
        headers: {
            'Content-Type': 'application/json'
        }
    });

    const data = await response.json();

    if (data.success === false) {
        throw new Error(`Не вдалося розрахувати вартість доставки`);
    }

    return data.data[0].Cost;
} catch (error) {
    console.error(error);
    alert(`Помилка: ${error.message}`);
}
}

// Обробка події натискання кнопки розрахунку вартості доставки
const shippingForm = document.getElementById('np-shipping-calculator-form');
shippingForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(shippingForm);

    try {
        const shippingCost = await calculateShippingCost(formData);

        if (shippingCost) {
            alert(`Вартість доставки: ${shippingCost} грн.`);
        } else {
            alert(`Не вдалося розрахувати вартість доставки. Будь ласка, перевірте дані.`);
        }
    } catch (error) {
        console.error(error);
        alert(`Помилка: ${error.message}`);
    }
});


jQuery(document).ready(function($) {
    $('input[type="text"], input[type="number"]').on('focus', function() {
        const parentElement = $(this).parent();
        if (parentElement) {
            parentElement.addClass('input-focused');
        }
    }).on('blur', function() {
        if ($(this).val().length === 0) {
            const parentElement = $(this).parent();
            if (parentElement) {
                parentElement.removeClass('input-focused');
            }
        }
    });
});
</script>

<div id="np-shipping-calculator-result"></div>
<?php
return ob_get_clean();
}
add_shortcode('np_shipping_calculator', 'np_shipping_calculator_shortcode');
