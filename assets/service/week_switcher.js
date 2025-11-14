import DateHelper from "../helper/date_helper.js";

export default class Week_switcher {
    static changeWeek(currentWeek, currentDate, offset) {
        let newWeek = currentWeek + offset;
        let newDate = new Date(currentDate);

        if (newWeek < 1) {
            newWeek = 52;
            newDate.setFullYear(newDate.getFullYear() - 1);
        } else if (newWeek > 52) {
            newWeek = 1;
            newDate.setFullYear(newDate.getFullYear() + 1);
        }
        return { week: newWeek, date: newDate };
    }

    static validateWeek(weekNum) {
        return !isNaN(weekNum) && weekNum >= 1 && weekNum <= 52;
    }

    static getWeekRangeText(week, year) {
        const firstDate = DateHelper.getFirstDateOfWeek(week, year);
        const lastDate = DateHelper.getLastDateOfWeek(week, year);

        return firstDate.toLocaleDateString('de-DE', { day: 'numeric', month: 'long' }).replace('.', '') +
            " - " +
            lastDate.toLocaleDateString('de-DE', { day: 'numeric', month: 'long' }).replace('.', '');
    }
}
