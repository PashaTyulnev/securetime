export default class Formater {
    static formatToJson(formData) {
        const obj = {};
        for (const [key, value] of formData.entries()) {
            if (key.includes('[')) {
                // z.B. address[street]
                const [mainKey, subKey] = key.split(/\[|\]/).filter(Boolean);
                obj[mainKey] = obj[mainKey] || {};
                obj[mainKey][subKey] = value;
            } else {
                obj[key] = value;
            }
        }
        return obj;
    }
}
