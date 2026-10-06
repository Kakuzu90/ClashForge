import GameProgressionGrid from '@/Components/game/GameProgressionGrid.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

type Unit = App.Domain.PlayerAccounts.Data.ProgressionUnitData;

const unit = (name: string, level: number, maxLevel: number, equipment: Unit[] = []): Unit => ({
    name,
    asset: { kind: 'unit', url: 'https://cdn.test/a.png', alt: name, short: 'A', width: 64, height: 64 },
    level,
    maxLevel,
    maxed: level >= maxLevel,
    locked: false,
    equipment,
});
const locked = (name: string): Unit => ({ ...unit(name, 0, 1), maxLevel: null, maxed: false, locked: true });

const grid = (units: Unit[]) =>
    mount(GameProgressionGrid, { props: { group: { key: 'heroes', label: 'Heroes', village: 'home', units } }, attachTo: document.body });

describe('GameProgressionGrid', () => {
    it('sets a maxed unit chip on fire, and only that one', () => {
        const [maxed, upgrading] = grid([unit('Archer Queen', 95, 95), unit('Barbarian King', 80, 95)]).findAll('li');

        expect(maxed.find('canvas').exists()).toBe(true);
        expect(maxed.text()).toContain('Archer Queen, level 95, maxed');
        expect(upgrading.find('canvas').exists()).toBe(false);
        expect(upgrading.text()).toContain('Barbarian King, level 80 of 95');
    });

    it('keeps the flames out of the accessibility tree', () => {
        const ring = grid([unit('Archer Queen', 95, 95)])
            .find('canvas')
            .element.closest('[aria-hidden="true"]');

        expect(ring).not.toBeNull();
    });

    it('opens a hero equipment like tapping the hero in the game', async () => {
        const wrapper = grid([unit('Archer Queen', 95, 95, [unit('Giant Arrow', 18, 18), unit('Archer Puppet', 9, 18)]), unit('Barbarian', 12, 12)]);
        const [hero, troop] = wrapper.findAll('li');

        expect(troop.find('button').exists()).toBe(false);
        expect(hero.text()).toContain('show equipment');

        await hero.find('button').trigger('click');
        const dialog = document.body.querySelector('[role="dialog"]');

        expect(dialog?.textContent).toContain('Archer Queen equipment');
        expect(dialog?.textContent).toContain('Giant Arrow, level 18, maxed');
        expect(dialog?.textContent).toContain('Archer Puppet, level 9 of 18');
        wrapper.unmount();
    });

    it('keeps the equipment modal mounted, so its art loads once', async () => {
        const wrapper = grid([unit('Archer Queen', 95, 95, [unit('Giant Arrow', 18, 18)])]);
        const button = wrapper.find('li button');

        await button.trigger('click');
        const image = document.body.querySelector('[role="dialog"] img');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();
        await button.trigger('click');

        expect(image).not.toBeNull();
        expect(document.body.querySelector('[role="dialog"] img')).toBe(image);
        wrapper.unmount();
    });

    it('grays out a locked unit, with no level chip and no equipment button', () => {
        const tile = grid([locked('Battle Blimp')]).find('li');

        expect(tile.text()).toContain('Battle Blimp, not unlocked');
        expect(tile.find('img').element.parentElement?.className).toContain('grayscale');
        expect(tile.find('canvas').exists()).toBe(false);
        expect(tile.find('button').exists()).toBe(false);
        expect(tile.find('[aria-hidden="true"]').text()).toBe('');
    });
});
