<?php
    if (file_exists(__DIR__."/../../pp-config.php")) {
        if (file_exists(__DIR__.'/../../maintenance.lock')) {
            if (file_exists(__DIR__.'/../../pp-include/pp-maintenance.php')) {

            }else{
                die('System is under maintenance. Please try again later.');
            }
            exit();
        }else{
            if (file_exists(__DIR__.'/../../pp-include/pp-controller.php')) {
                if (file_exists(__DIR__.'/../../pp-include/pp-view.php')) {
    
                }else{
                    echo 'System is under maintenance. Please try again later.';
                    exit();
                }
            }else{
                echo 'System is under maintenance. Please try again later.';
                exit();
            }
            
            if (file_exists(__DIR__.'/../../pp-include/pp-model.php')) {
                include(__DIR__."/../../pp-include/pp-model.php");
            }else{
                echo 'System is under maintenance. Please try again later.';
                exit();
            }
        }
    }else{
        echo 'System is under maintenance. Please try again later.';
        exit();
    }
    
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }
    
    if(isset($global_user_login) && $global_user_login == true){
        $page = $_GET['page'] ?? '';
        if (!$page) {
            exit("Missing plugin page.");
        }
        
        list($mainFolder, $pluginFolder) = explode('--', $page);
        if (!preg_match('/^[a-zA-Z0-9-_]+$/', $mainFolder) || !preg_match('/^[a-zA-Z0-9-_]+$/', $pluginFolder)) {
            exit("Invalid plugin path.");
        }
        
        $response_plugin = json_decode(getData($db_prefix.'plugins', 'WHERE plugin_slug="'.$pluginFolder.'" AND status="active"'), true);
        if($response_plugin['status'] == true){
            
        }else{
            exit("Invalid plugin path.");
        }
        
        $pluginPath = __DIR__ . "/../../pp-content/plugins/{$mainFolder}/{$pluginFolder}";
        $classFile = "{$pluginPath}/{$pluginFolder}-class.php";
        
        if (!file_exists($classFile)) {
            exit("Plugin main file not found.");
        }
        
        require_once $classFile;
        
        // Expected admin function: plugin-folder name + _admin_page
        $function = str_replace('-', '_', $pluginFolder) . '_admin_page';
        if (function_exists($function)) {
            call_user_func($function);
            if ($mainFolder === 'payment-gateway') {
                $settings = pp_get_plugin_setting($pluginFolder, false);
                $gatewayCurrency = pp_normalize_currency_code($settings['currency'] ?? '');
                $gatewayCategory = trim((string) ($settings['category'] ?? ''));
                $directCurrencies = implode(',', pp_normalize_gateway_currency_list($settings['direct_currencies'] ?? [], $gatewayCurrency));
                $supportedCurrencies = implode(',', pp_normalize_gateway_currency_list($settings['supported_currencies'] ?? [], ''));
                $currencyOptions = [];
                $currencyResponse = json_decode(getData($db_prefix.'currency', 'ORDER BY currency_code ASC'), true);
                if (($currencyResponse['status'] ?? false) === true) {
                    foreach ($currencyResponse['response'] as $currencyRow) {
                        $currencyCode = pp_normalize_currency_code($currencyRow['currency_code'] ?? '');
                        if ($currencyCode !== '') {
                            $currencyOptions[$currencyCode] = trim((string) ($currencyRow['currency_name'] ?? ''));
                        }
                    }
                }
                if ($gatewayCurrency !== '' && !isset($currencyOptions[$gatewayCurrency])) {
                    $currencyOptions[$gatewayCurrency] = $gatewayCurrency;
                }
                $categoryOptions = [];
                $categoryResponse = json_decode(getData($db_prefix.'plugins', 'WHERE plugin_dir="payment-gateway"'), true);
                if (($categoryResponse['status'] ?? false) === true) {
                    foreach ($categoryResponse['response'] as $pluginRow) {
                        $pluginSettings = json_decode($pluginRow['plugin_array'] ?? '', true);
                        $category = trim((string) ($pluginSettings['category'] ?? ''));
                        if ($category !== '' && $category !== '--') {
                            $categoryOptions[$category] = $category;
                        }
                    }
                }
                foreach (['Mobile Banking', 'IBanking', 'International', 'Crypto'] as $defaultCategory) {
                    if (!isset($categoryOptions[$defaultCategory])) {
                        $categoryOptions[$defaultCategory] = $defaultCategory;
                    }
                }
                if ($gatewayCategory !== '' && !isset($categoryOptions[$gatewayCategory])) {
                    $categoryOptions[$gatewayCategory] = $gatewayCategory;
                }
                ksort($categoryOptions, SORT_NATURAL | SORT_FLAG_CASE);
                $currencyOptionsJson = json_encode($currencyOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                $categoryOptionsJson = json_encode(array_values($categoryOptions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                $gatewayCurrency = htmlspecialchars($gatewayCurrency, ENT_QUOTES, 'UTF-8');
                $gatewayCategory = htmlspecialchars($gatewayCategory, ENT_QUOTES, 'UTF-8');
                $directCurrencies = htmlspecialchars($directCurrencies, ENT_QUOTES, 'UTF-8');
                $supportedCurrencies = htmlspecialchars($supportedCurrencies, ENT_QUOTES, 'UTF-8');
                $allowConversion = (($settings['allow_currency_conversion'] ?? 'disable') === 'enable') ? 'enable' : 'disable';
                $showUnavailable = (($settings['show_when_unavailable'] ?? 'enable') === 'disable') ? 'disable' : 'enable';
                ?>
                <script>
                    (function() {
                        const form = document.querySelector('form');
                        const currencyInput = form ? form.querySelector('[name="currency"]') : null;
                        const categoryInput = form ? form.querySelector('[name="category"]') : null;
                        if (!form || !currencyInput || form.querySelector('[name="allow_currency_conversion"]')) return;

                        const currencyOptions = <?php echo $currencyOptionsJson ?: '{}'; ?>;
                        const categoryOptions = <?php echo $categoryOptionsJson ?: '[]'; ?>;
                        const createSelectFromInput = (input, options, selectedValue, placeholder) => {
                            const select = document.createElement('select');
                            select.className = input.className || 'form-control';
                            select.name = input.name;
                            select.id = input.id;
                            const emptyOption = document.createElement('option');
                            emptyOption.value = '';
                            emptyOption.textContent = placeholder;
                            select.appendChild(emptyOption);

                            Object.entries(options).forEach(([value, label]) => {
                                const option = document.createElement('option');
                                option.value = value;
                                option.textContent = label && label !== value ? `${value} - ${label}` : value;
                                if (value === selectedValue) option.selected = true;
                                select.appendChild(option);
                            });

                            input.replaceWith(select);
                            return select;
                        };
                        const createCategorySelect = (input, options, selectedValue) => {
                            const select = document.createElement('select');
                            select.className = input.className || 'form-control';
                            select.name = input.name;
                            select.id = input.id;
                            const emptyOption = document.createElement('option');
                            emptyOption.value = '';
                            emptyOption.textContent = 'Select category';
                            select.appendChild(emptyOption);

                            options.forEach((value) => {
                                const option = document.createElement('option');
                                option.value = value;
                                option.textContent = value;
                                if (value === selectedValue) option.selected = true;
                                select.appendChild(option);
                            });

                            input.replaceWith(select);
                            return select;
                        };

                        const currencyColumn = currencyInput.closest('.col-sm-6, .col-md-6, .col-lg-6') || currencyInput.parentElement;
                        const selectedCurrency = "<?php echo $gatewayCurrency; ?>";
                        const currencySelect = createSelectFromInput(currencyInput, currencyOptions, selectedCurrency, 'Select currency');
                        const currencyHelp = currencySelect.closest('.col-sm-6, .col-md-6, .col-lg-6')?.querySelector('.text-secondary');
                        if (currencyHelp) currencyHelp.textContent = 'Gateway/fallback currency. Converted payments will be converted to this currency.';

                        if (categoryInput) {
                            const selectedCategory = "<?php echo $gatewayCategory; ?>";
                            const categorySelect = createCategorySelect(categoryInput, categoryOptions, selectedCategory);
                            const categoryHelp = categorySelect.closest('.col-sm-6, .col-md-6, .col-lg-6')?.querySelector('.text-secondary');
                            if (categoryHelp) categoryHelp.textContent = 'Gateway category used to group this gateway on checkout.';
                        }
                        const wrapper = document.createElement('div');
                        wrapper.className = 'row mb-4';
                        wrapper.innerHTML = `
                            <div class="col-sm-6">
                                <label for="direct_currencies" class="col-sm-12 col-form-label form-label">Direct currencies</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="direct_currencies" id="direct_currencies" value="<?php echo $directCurrencies; ?>" placeholder="<?php echo $gatewayCurrency; ?>">
                                </div>
                                <div class="text-secondary mt-2">Currencies this gateway accepts directly without conversion. Example: USD,GBP</div>
                            </div>
                            <div class="col-sm-6">
                                <label for="supported_currencies" class="col-sm-12 col-form-label form-label">Supported currencies</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="supported_currencies" id="supported_currencies" value="<?php echo $supportedCurrencies; ?>" placeholder="BDT,EUR,INR">
                                </div>
                                <div class="text-secondary mt-2">Currencies allowed for conversion into the gateway currency.</div>
                            </div>
                            <div class="col-sm-6 mt-4">
                                <label for="allow_currency_conversion" class="col-sm-12 col-form-label form-label">Currency conversion</label>
                                <div class="input-group">
                                    <select class="form-control" name="allow_currency_conversion" id="allow_currency_conversion">
                                        <option value="disable" <?php echo $allowConversion === 'disable' ? 'selected' : ''; ?>>Disable</option>
                                        <option value="enable" <?php echo $allowConversion === 'enable' ? 'selected' : ''; ?>>Enable</option>
                                    </select>
                                </div>
                                <div class="text-secondary mt-2">Enable to convert supported currencies to the gateway currency.</div>
                            </div>
                            <div class="col-sm-6 mt-4">
                                <label for="show_when_unavailable" class="col-sm-12 col-form-label form-label">Show unavailable gateway</label>
                                <div class="input-group">
                                    <select class="form-control" name="show_when_unavailable" id="show_when_unavailable">
                                        <option value="enable" <?php echo $showUnavailable === 'enable' ? 'selected' : ''; ?>>Enable</option>
                                        <option value="disable" <?php echo $showUnavailable === 'disable' ? 'selected' : ''; ?>>Disable</option>
                                    </select>
                                </div>
                                <div class="text-secondary mt-2">Show as disabled when currency or amount is not supported.</div>
                            </div>
                        `;

                        const row = currencyColumn ? currencyColumn.closest('.row') : null;
                        if (row && row.parentNode) {
                            row.parentNode.insertBefore(wrapper, row.nextSibling);
                        } else {
                            form.appendChild(wrapper);
                        }
                    })();
                </script>
                <?php
            }
        } else {
            echo "Admin page function not found in plugin.";
        }
    }
?>