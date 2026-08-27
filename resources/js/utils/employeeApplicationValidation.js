const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

export const WIZARD_STEPS = [
    { id: 1, key: 'personal', label: 'Datos personales', short: 'Personal' },
    { id: 2, key: 'contact', label: 'Contacto', short: 'Contacto' },
    { id: 3, key: 'documentation', label: 'Documentación', short: 'Docs' },
    { id: 4, key: 'employment', label: 'Laboral y banco', short: 'Laboral' },
    { id: 5, key: 'summary', label: 'Resumen y firma', short: 'Firma' },
];

export const MARITAL_STATUSES = [
    { value: 'soltero', label: 'Soltero/a' },
    { value: 'casado', label: 'Casado/a' },
    { value: 'divorciado', label: 'Divorciado/a' },
    { value: 'viudo', label: 'Viudo/a' },
    { value: 'pareja_hecho', label: 'Pareja de hecho' },
];

export const CONTRACT_TYPES = [
    { value: 'indefinido', label: 'Indefinido' },
    { value: 'temporal', label: 'Temporal' },
    { value: 'practicas', label: 'Prácticas' },
    { value: 'formacion', label: 'Formación' },
];

export const WORK_SCHEDULES = [
    { value: 'completa', label: 'Jornada completa' },
    { value: 'parcial', label: 'Jornada parcial' },
    { value: 'reducida', label: 'Jornada reducida' },
];

export const WORK_PERMIT_TYPES = [
    { value: 'tie', label: 'TIE — Tarjeta de Identidad de Extranjero' },
    { value: 'visado', label: 'Visado con habilitación para trabajar' },
    { value: 'permiso_trabajo', label: 'Autorización de residencia y trabajo' },
];

/**
 * Versión del texto de consentimiento aceptado. Al cambiar el texto hay que
 * subir este valor para poder distinguir qué aceptó cada persona.
 */
export const GDPR_VERSION = '2026-08';

export const IRPF_FAMILY_SITUATIONS = [
    { value: '1', label: '1 — Soltero/a, viudo/a, divorciado/a con hijos menores de 18' },
    { value: '2', label: '2 — Casado/a, no separado legalmente' },
    { value: '3', label: '3 — Otros (convivencia, situaciones especiales)' },
];

export const NATIONALITIES = [
    'Española',
    'Andorrana',
    'Argentina',
    'Boliviana',
    'Brasileña',
    'Chilena',
    'Colombiana',
    'Ecuatoriana',
    'Francesa',
    'Alemana',
    'Italiana',
    'Marroquí',
    'Mexicana',
    'Peruana',
    'Portuguesa',
    'Rumana',
    'Ucraniana',
    'Venezolana',
    'Otra',
];

export function normalizeDocument(value) {
    return (value || '').toUpperCase().replace(/[\s-]/g, '');
}

export function validateDni(dni) {
    const value = normalizeDocument(dni);
    const match = value.match(/^(\d{8})([A-Z])$/);
    if (!match) return false;
    return DNI_LETTERS[parseInt(match[1], 10) % 23] === match[2];
}

export function validateNie(nie) {
    const value = normalizeDocument(nie);
    const match = value.match(/^([XYZ])(\d{7})([A-Z])$/);
    if (!match) return false;
    const prefix = { X: '0', Y: '1', Z: '2' }[match[1]];
    const number = prefix + match[2];
    return DNI_LETTERS[parseInt(number, 10) % 23] === match[3];
}

export function validateDocument(type, number) {
    const value = normalizeDocument(number);
    if (!value) return false;
    switch (type) {
        case 'dni':
        case 'nif':
            return validateDni(value);
        case 'nie':
            return validateNie(value);
        case 'ss':
            return /^[0-9]{12}$/.test(value);
        default:
            return false;
    }
}

export function normalizeNaf(naf) {
    return (naf || '').toUpperCase().replace(/[\s/.-]/g, '');
}

/**
 * Valida el Número de Afiliación a la Seguridad Social.
 * 2 dígitos de provincia + 8 de secuencial + 2 de control (módulo 97).
 * Si el secuencial es menor de 10.000.000 no se concatena: la provincia
 * se multiplica por 10.000.000 y se le suma el secuencial.
 */
export function validateNaf(naf, { requireHolder = true } = {}) {
    let clean = normalizeNaf(naf);

    if (requireHolder) {
        if (!clean.endsWith('T')) return false;
        clean = clean.slice(0, -1);
    } else if (/[A-Z]$/.test(clean)) {
        clean = clean.slice(0, -1);
    }

    if (!/^\d{12}$/.test(clean)) return false;

    const province = parseInt(clean.slice(0, 2), 10);
    const sequential = parseInt(clean.slice(2, 10), 10);
    const control = parseInt(clean.slice(10, 12), 10);

    if (province < 1 || province > 56) return false;

    const base =
        sequential < 10000000
            ? province * 10000000 + sequential
            : parseInt(clean.slice(0, 10), 10);

    return base % 97 === control;
}

export function isBeneficiaryNaf(naf) {
    return /[A-SU-Z]$/.test(normalizeNaf(naf));
}

export function validateIban(iban) {
    const value = (iban || '').toUpperCase().replace(/\s+/g, '');
    if (!/^ES\d{22}$/.test(value)) return false;

    const rearranged = value.slice(4) + value.slice(0, 4);
    let numeric = '';
    for (const char of rearranged) {
        numeric += /[A-Z]/.test(char) ? (char.charCodeAt(0) - 55).toString() : char;
    }

    let remainder = 0;
    for (const digit of numeric) {
        remainder = (remainder * 10 + parseInt(digit, 10)) % 97;
    }

    return remainder === 1;
}

export function detectBankName(iban) {
    const value = (iban || '').toUpperCase().replace(/\s+/g, '');
    if (!value.startsWith('ES') || value.length < 8) return null;

    const code = value.slice(4, 8);
    const banks = {
        '0049': 'Banco Santander',
        '0182': 'BBVA',
        '2100': 'CaixaBank',
        '0081': 'Banco Sabadell',
        '2038': 'Bankinter',
        '0128': 'Bankia / CaixaBank',
        '0075': 'Banco Popular',
        '0239': 'EVO Banco',
        '1465': 'ING',
        '0073': 'Openbank',
        '0216': 'Targobank',
        '3058': 'Cajamar',
        '2085': 'Ibercaja',
        '2080': 'Abanca',
    };

    return banks[code] || null;
}

export function validateSpanishPhone(phone) {
    const digits = (phone || '').replace(/\D/g, '');
    return /^[67]\d{8}$/.test(digits) || /^34[67]\d{8}$/.test(digits);
}

export function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email || '');
}

export function extractDocumentFromText(text) {
    if (!text) return null;
    const upper = text.toUpperCase().replace(/[^A-Z0-9]/g, ' ');

    const dniMatch = upper.match(/\b(\d{8})\s*([A-Z])\b/);
    if (dniMatch && validateDni(dniMatch[1] + dniMatch[2])) {
        return { type: 'dni', number: dniMatch[1] + dniMatch[2] };
    }

    const nieMatch = upper.match(/\b([XYZ])\s*(\d{7})\s*([A-Z])\b/);
    if (nieMatch && validateNie(nieMatch[1] + nieMatch[2] + nieMatch[3])) {
        return { type: 'nie', number: nieMatch[1] + nieMatch[2] + nieMatch[3] };
    }

    return null;
}

export function validateStep(step, data) {
    const errors = {};

    if (step === 1) {
        if (!data.name?.trim()) errors.name = 'El nombre es obligatorio.';
        if (!data.surname?.trim()) errors.surname = 'Los apellidos son obligatorios.';
        if (!data.birth_date) errors.birth_date = 'La fecha de nacimiento es obligatoria.';
        else if (new Date(data.birth_date) >= new Date()) errors.birth_date = 'Debe ser una fecha pasada.';
        if (!data.nationality) errors.nationality = 'Selecciona la nacionalidad.';
        if (!data.marital_status) errors.marital_status = 'Selecciona el estado civil.';
        if (data.dependents_count === '' || data.dependents_count < 0) errors.dependents_count = 'Indica hijos a cargo (0 si no aplica).';
    }

    if (step === 2) {
        if (!data.street?.trim()) errors.street = 'La dirección es obligatoria.';
        if (!/^\d{5}$/.test(data.postal_code || '')) errors.postal_code = 'El código postal debe tener 5 dígitos.';
        if (!data.city?.trim()) errors.city = 'El municipio es obligatorio.';
        if (!data.province?.trim()) errors.province = 'La provincia es obligatoria.';
        if (!validateSpanishPhone(data.phone)) errors.phone = 'Introduce un móvil español válido (9 dígitos).';
        if (!validateEmail(data.email)) errors.email = 'Introduce un email válido.';
        if (!data.phone_verified) errors.phone = errors.phone || 'Verifica tu teléfono con el código SMS.';
    }

    if (step === 3) {
        if (!data.document_type) errors.document_type = 'Selecciona el tipo de documento.';
        if (!data.document_number?.trim()) errors.document_number = 'Introduce el número de documento.';
        else if (!validateDocument(data.document_type, data.document_number)) {
            errors.document_number = 'Documento inválido o letra de control incorrecta.';
        }
        if (!data.document_expiry_date) errors.document_expiry_date = 'Indica la fecha de caducidad.';
        else if (new Date(data.document_expiry_date) <= new Date()) errors.document_expiry_date = 'El documento debe estar en vigor.';

        if (data.has_social_security) {
            const naf = data.social_security_number || '';

            if (!naf.trim()) {
                errors.social_security_number = 'Introduce tu número de afiliación a la Seguridad Social.';
            } else if (isBeneficiaryNaf(naf)) {
                errors.social_security_number =
                    'Ese número es de beneficiario. Para el alta hace falta el de titular, que termina en T.';
            } else if (!normalizeNaf(naf).endsWith('T')) {
                errors.social_security_number = 'El número debe terminar en T (titular).';
            } else if (!validateNaf(naf)) {
                errors.social_security_number = 'El número no es válido: revisa los dígitos de control.';
            }
        } else {
            if (!data.work_permit_type) errors.work_permit_type = 'Indica qué documento acredita tu permiso de trabajo.';
            if (!data.work_permit_number?.trim()) errors.work_permit_number = 'Introduce el NIE que figura en el documento.';
            else if (!validateNie(data.work_permit_number)) errors.work_permit_number = 'El NIE no es válido.';
            if (!data.work_permit_expiry) errors.work_permit_expiry = 'Indica la fecha de caducidad del permiso.';
            else if (new Date(data.work_permit_expiry) <= new Date()) errors.work_permit_expiry = 'El permiso debe estar en vigor.';
            if (!data.passport_number?.trim()) errors.passport_number = 'Introduce el número de pasaporte.';
            if (!data.passport_expiry) errors.passport_expiry = 'Indica la caducidad del pasaporte.';
            else if (new Date(data.passport_expiry) <= new Date()) errors.passport_expiry = 'El pasaporte debe estar en vigor.';
        }

        const minFiles = data.has_social_security ? 1 : 2;
        if ((data.document_images?.length || 0) < minFiles) {
            errors.document_images = data.has_social_security
                ? 'Adjunta al menos un documento.'
                : 'Adjunta el pasaporte completo y la TIE o el visado.';
        }
    }

    if (step === 4) {
        if (!data.position) errors.position = 'Indica el puesto.';
        if (!data.department) errors.department = 'Indica el departamento.';
        if (!data.start_date) errors.start_date = 'Indica la fecha de incorporación.';
        if (!data.contract_type) errors.contract_type = 'Selecciona el tipo de contrato.';
        if (!data.work_schedule) errors.work_schedule = 'Selecciona la jornada.';
        if (!validateIban(data.iban)) errors.iban = 'IBAN español no válido.';
        if (!data.irpf_family_situation) errors.irpf_family_situation = 'Selecciona la situación familiar (modelo 145).';
    }

    if (step === 5) {
        if (!data.gdpr_accepted) {
            errors.gdpr_accepted = 'Debes aceptar el tratamiento de tus datos para poder continuar.';
        }
        if (!data.signature) errors.signature = 'Debes firmar digitalmente.';
    }

    return errors;
}

export function createEmptyForm() {
    return {
        name: '',
        surname: '',
        birth_date: '',
        nationality: 'Española',
        marital_status: '',
        dependents_count: 0,
        disability_recognized: false,
        street: '',
        postal_code: '',
        city: '',
        province: '',
        phone: '',
        phone_verified: false,
        email: '',
        document_type: 'dni',
        document_number: '',
        document_expiry_date: '',
        has_social_security: true,
        social_security_number: '',
        work_permit_type: '',
        work_permit_number: '',
        work_permit_expiry: '',
        passport_number: '',
        passport_expiry: '',
        document_ocr_verified: false,
        document_images: [],
        position: '',
        department: '',
        start_date: '',
        contract_type: '',
        work_schedule: '',
        iban: '',
        bank_name: '',
        irpf_family_situation: '',
        irpf_children_under_3: 0,
        irpf_disability_degree: 0,
        irpf_additional_withholding: '',
        notes: '',
        gdpr_accepted: false,
        signature: '',
    };
}
