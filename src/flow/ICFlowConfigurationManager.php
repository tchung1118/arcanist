<?php

final class ICFlowConfigurationManager extends Phobject {

  private $arcanistConfig;
  private $writtenConfig = null;

  public function setArcanistConfigurationManager(
    ArcanistConfigurationManager $arcanist_config) {
    $this->arcanistConfig = $arcanist_config;
    return $this;
  }

  private function getConfig() {
    if ($this->writtenConfig === null) {
      $user_config = $this->arcanistConfig->readUserArcConfig();
      $this->writtenConfig = idx($user_config, 'flow', []);
    }
    return $this->writtenConfig;
  }

  private function getFieldConfigs() {
    return idx($this->getConfig(), 'fields', []);
  }

  private function getFieldConfig($field) {
    return idx($this->getFieldConfigs(), $field, []);
  }

  private function getFieldConfigOption($field, $option) {
    return idx($this->getFieldConfig($field), $option);
  }

  private function setConfig(array $config) {
    $user_config = $this->arcanistConfig->readUserArcConfig();
    $user_config['flow'] = $config;
    $this->arcanistConfig->writeUserArcConfig($user_config);
    $this->writtenConfig = $config;
  }

  private function setFieldConfigs(array $field_configs) {
    $config = $this->getConfig();
    $config['fields'] = $field_configs;
    $this->setConfig($config);
  }

  private function setFieldConfig($field, array $field_config) {
    $field_configs = $this->getFieldConfigs();
    $field_configs[$field] = $field_config;
    $this->setFieldConfigs($field_configs);
  }

  private function setFieldConfigOption($field, $option, $value) {
    $field_config = $this->getFieldConfig($field);
    $field_config[$option] = $value;
    $this->setFieldConfig($field, $field_config);
  }

  public function getAllFields() {
    $field_configs = $this->getFieldConfigs();
    $field_keys = ICFlowField::getAllFieldKeys();
    $fields = [];
    foreach ($field_keys as $field_key) {
      $field = ICFlowField::newField($field_key);
      if ($field_config = idx($field_configs, $field_key)) {
        $field->loadConfiguration($field_config);
      }
      $fields[$field_key] = $field;
    }
    $fields = msort($fields, 'getFieldOrder');
    return $fields;
  }

  public function getEnabledFields() {
    return mfilter($this->getAllFields(), 'isEnabled');
  }

  public function getConfigValue($key, $default = null) {
    return idx($this->getConfig(), $key, $default);
  }

  public function setConfigValue($key, $value) {
    $config = $this->getConfig();
    $config[$key] = $value;
    $this->setConfig($config);
    return $this;
  }

  public function configureFields(array $field_keys) {
    $all_keys = ICFlowField::getAllFieldKeys();
    $non_fields = array_diff($field_keys, $all_keys);
    if ($non_fields) {
      throw new Exception(pht(
        'Invalid field names specified: %s',
        implode(', ', $non_fields)));
    }
    foreach ($all_keys as $field_key) {
      if (in_array($field_key, $field_keys)) {
        $index = array_search($field_key, $field_keys);
        $this->setFieldConfigOption($field_key, 'order', $index);
        $this->setFieldConfigOption($field_key, 'enabled', true);
      } else {
        $this->setFieldConfigOption($field_key, 'enabled', false);
      }
    }
  }

  public function configureFieldOption($field, $option, $value) {
    $all_fields = $this->getAllFields();
    $instance = idx($all_fields, $field);
    if (!$instance) {
      throw new Exception(pht('No field named "%s" exists.', $field));
    }
    $value = $instance->validateOption($option, $value);
    $this->setFieldConfigOption($field, $option, $value);
  }

}
