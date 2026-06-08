//
//  ComponentGating.swift
//  ElectricSim — Core
//
//  კომპონენტების ფასიანობის (free/Pro) ერთადერთი წყარო (single source of truth).
//  იყენებს ორივე: UI (პალიტრის ჩაკეტვა) და ტესტები (უფასო დონის სისრულის შემოწმება).
//
//  წესი: ფასიანობა იმართება *დონის* დონეზე (Level.tier). თუ მომხმარებელს დონის
//  გახსნა შეუძლია, მისი მთელი პალიტრა ხელმისაწვდომია — ცალკეულ კომპონენტზე
//  Pro-ჩაკეტვა აღარ ხდება. ეს უზრუნველყოფს, რომ უფასო დონე ყოველთვის გასავლელია.
//

import Foundation

public enum ComponentGating {

    /// უფასო მომხმარებლისთვის კომპონენტი ხელმისაწვდომია თუ არა (kind-ის მიხედვით).
    /// ამჟამად ყველა კომპონენტი ხელმისაწვდომია იმ დონის ფარგლებში, რომელიც
    /// მომხმარებელს უკვე გახსნილი აქვს (ფასიანობა დონის tier-ით განისაზღვრება).
    public static func isAvailableForFree(kind: ComponentKind) -> Bool {
        return true
    }

    /// დონის პალიტრის კონკრეტული ელემენტი ხელმისაწვდომია თუ არა უფასო მომხმარებლისთვის.
    public static func isPaletteEntryAvailableForFree(_ entry: PaletteEntry,
                                                      templates: [String: ComponentTemplate]) -> Bool {
        guard let kind = templates[entry.templateId]?.kind else { return true }
        return isAvailableForFree(kind: kind)
    }
}
