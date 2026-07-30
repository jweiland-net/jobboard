class FormEngineLocalizedDecimal {
  static initialize(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) {
      return;
    }
    field.addEventListener('change', () => {
      field.value = FormEngineLocalizedDecimal.normalize(field.value);
    });
  }

  static normalize(rawValue) {
    let value = String(rawValue).trim();
    if (value === '') {
      return value;
    }

    const negative = value.startsWith('-');
    if (negative) {
      value = value.slice(1);
    }

    const decimalPos = Math.max(value.lastIndexOf(','), value.lastIndexOf('.'));
    let integerPart = value;
    let fractionPart = '0';
    if (decimalPos !== -1) {
      integerPart = value.slice(0, decimalPos);
      fractionPart = value.slice(decimalPos + 1) || '0';
    }
    integerPart = integerPart.split(',').join('').split('.').join('');

    const number = parseFloat(integerPart + '.' + fractionPart);
    if (Number.isNaN(number)) {
      return '';
    }

    return (negative ? -number : number).toFixed(2);
  }
}

export default FormEngineLocalizedDecimal;
