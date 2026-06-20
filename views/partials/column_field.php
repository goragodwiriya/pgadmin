<?php
/**
 * @var array $col
 * @var string $fieldName  e.g. data[id] or where[id]
 * @var mixed $value
 * @var bool $showNullOption
 */
$inputType = $col['input_type'] ?? 'string';
$name = $col['name'];
$id = 'field_' . preg_replace('/[^a-z0-9_]/i', '_', $fieldName);
$strVal = $value === null ? '' : (string) $value;

if ($inputType === 'boolean'):
    $checked = in_array(strtolower($strVal), ['1', 't', 'true', 'yes'], true);
?>
<div class="form-group">
    <label for="<?= e($id) ?>"><?= e($name) ?></label>
    <select name="<?= e($fieldName) ?>" id="<?= e($id) ?>">
        <?php if ($showNullOption && ($col['nullable'] ?? 'YES') === 'YES'): ?>
        <option value="" <?= $strVal === '' ? 'selected' : '' ?>>NULL</option>
        <?php endif; ?>
        <option value="true" <?= $checked ? 'selected' : '' ?>>true</option>
        <option value="false" <?= $strVal !== '' && !$checked ? 'selected' : '' ?>>false</option>
    </select>
</div>
<?php elseif ($inputType === 'date'):
    $dateVal = $strVal !== '' ? substr($strVal, 0, 10) : '';
?>
<div class="form-group">
    <label for="<?= e($id) ?>"><?= e($name) ?> <small class="text-muted">(วันที่)</small></label>
    <input type="text" name="<?= e($fieldName) ?>" id="<?= e($id) ?>" value="<?= e($dateVal) ?>"
           class="input-date" placeholder="เลือกวันที่" autocomplete="off">
</div>
<?php elseif ($inputType === 'datetime'):
    $dtVal = $strVal;
    if ($dtVal !== '' && !str_contains($dtVal, 'T')) {
        $dtVal = str_replace(' ', 'T', substr($dtVal, 0, 19));
    }
?>
<div class="form-group">
    <label for="<?= e($id) ?>"><?= e($name) ?> <small class="text-muted">(วันเวลา)</small></label>
    <input type="text" name="<?= e($fieldName) ?>" id="<?= e($id) ?>" value="<?= e($dtVal) ?>"
           class="input-datetime" placeholder="เลือกวันและเวลา" autocomplete="off">
</div>
<?php elseif ($inputType === 'time'):
    $timeVal = $strVal !== '' ? substr($strVal, 0, 8) : '';
?>
<div class="form-group">
    <label for="<?= e($id) ?>"><?= e($name) ?> <small class="text-muted">(เวลา)</small></label>
    <input type="text" name="<?= e($fieldName) ?>" id="<?= e($id) ?>" value="<?= e($timeVal) ?>"
           class="input-time" placeholder="เลือกเวลา" autocomplete="off">
</div>
<?php elseif ($inputType === 'json'): ?>
<div class="form-group">
    <label for="<?= e($id) ?>"><?= e($name) ?> <small class="text-muted">(JSON)</small></label>
    <textarea name="<?= e($fieldName) ?>" id="<?= e($id) ?>" rows="3"><?= e($strVal) ?></textarea>
</div>
<?php elseif ($inputType === 'text'): ?>
<div class="form-group">
    <label for="<?= e($id) ?>"><?= e($name) ?></label>
    <textarea name="<?= e($fieldName) ?>" id="<?= e($id) ?>" rows="2"><?= e($strVal) ?></textarea>
</div>
<?php elseif ($inputType === 'number'): ?>
<div class="form-group">
    <label for="<?= e($id) ?>"><?= e($name) ?></label>
    <input type="number" step="any" name="<?= e($fieldName) ?>" id="<?= e($id) ?>" value="<?= e($strVal) ?>">
</div>
<?php else: ?>
<div class="form-group">
    <label for="<?= e($id) ?>"><?= e($name) ?></label>
    <input type="text" name="<?= e($fieldName) ?>" id="<?= e($id) ?>" value="<?= e($strVal) ?>">
</div>
<?php endif; ?>
