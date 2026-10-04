import GameProgressionGrid from '@/Components/game/GameProgressionGrid.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

type Unit = App.Domain.PlayerAccounts.Data.ProgressionUnitData;

const unit = (name: string, level: number, maxLevel: number): Unit => ({
    name,
    asset: { kind: 'unit', url: 'https://cdn.test/a.png', alt: name, short: 'A', width: 64, height: 64 },
    level,
    maxLevel,
    maxed: level >= maxLevel,
});

const grid = () =>
    mount(GameProgressionGrid, {
        props: { group: { key: 'heroes', label: 'Heroes', units: [unit('Archer Queen', 95, 95), unit('Barbarian King', 80, 95)] } },
    });

describe('GameProgressionGrid', () => {
    it('sets a maxed unit chip on fire, and only that one', () => {
        const [maxed, upgrading] = grid().findAll('li');

        expect(maxed.find('canvas').exists()).toBe(true);
        expect(maxed.text()).toContain('Max');
        expect(upgrading.find('canvas').exists()).toBe(false);
        expect(upgrading.text()).toContain('Lv 80');
    });

    it('keeps the flames out of the accessibility tree', () => {
        const ring = grid().find('canvas').element.closest('[aria-hidden="true"]');

        expect(ring).not.toBeNull();
        expect(grid().find('li').text()).toContain('Archer Queen, level 95, maxed');
    });
});
