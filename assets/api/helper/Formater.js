export default class Formater {

    static formatToJson(formData) {

        console.log("FORMATIERE JSON")
        const jsonData = {};

        // Sammle alle Array-Keys (z.B. roles[], requiredPositions[])
        const arrayKeys = new Set();
        for (const key of formData.keys()) {
            if (key.endsWith('[]')) {
                arrayKeys.add(key);
            }
        }

        // Verarbeite normale Felder
        for (const [key, value] of formData.entries()) {
            // Überspringe Array-Felder, die separat behandelt werden
            if (key.endsWith('[]')) {
                continue;
            }

            if (value === '' || value === null || value === undefined) {
                continue;
            }

            let processedValue = value;

            // ---- Boolean Erkennung ----
            if (value === "true") {
                processedValue = true;
            } else if (value === "false") {
                processedValue = false;
            }

            // ---- Integer-Erkennung für 'id' ----
            const isIdKey = key.split(/[\[\]]/).filter(Boolean).pop() === "id";
            if (isIdKey) {
                processedValue = parseInt(value, 10);
            }

            // ---- Keys mit [] Struktur parsen ----
            const keys = [];
            const regex = /([^\[\]]+)|\[([^\[\]]*)\]/g;
            let match;
            while ((match = regex.exec(key)) !== null) {
                if (match[1]) {
                    keys.push(match[1]);
                } else if (match[2]) {
                    keys.push(match[2]);
                }
            }

            let current = jsonData;
            for (let i = 0; i < keys.length - 1; i++) {
                if (!current[keys[i]]) {
                    current[keys[i]] = {};
                }
                current = current[keys[i]];
            }

            const lastKey = keys[keys.length - 1];

            // ---- Integer-Erkennung auch für verschachtelte id-Keys ----
            if (lastKey === "id") {
                processedValue = parseInt(processedValue, 10);
            }

            current[lastKey] = processedValue;
        }

        // Verarbeite Array-Felder
        for (const arrayKey of arrayKeys) {
            const arrayName = arrayKey.replace('[]', '');
            const values = formData.getAll(arrayKey);
            if (values.length > 0) {
                jsonData[arrayName] = values;
            }
        }

        return jsonData;
    }
}
