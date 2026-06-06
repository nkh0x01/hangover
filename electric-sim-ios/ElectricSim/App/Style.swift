//
//  Style.swift
//  ElectricSim
//
//  ვიზუალური დამხმარეები: სადენის ფერები, კომპონენტის იკონები.
//

import SwiftUI

extension WireColor {
    /// ეკრანის ფერი (IEC ჰარმონიზებული).
    var swiftUIColor: Color {
        switch self {
        case .yellowGreen: return Color(red: 0.55, green: 0.78, blue: 0.10)
        case .blue:        return Color(red: 0.10, green: 0.45, blue: 0.95)
        case .brown:       return Color(red: 0.55, green: 0.35, blue: 0.18)
        case .black:       return Color(white: 0.12)
        case .grey:        return Color(white: 0.55)
        }
    }
}

extension Conductor {
    var swiftUIColor: Color { WireColor.standard(for: self).swiftUIColor }
}

extension ComponentKind {
    var sfSymbol: String {
        switch self {
        case .supply:     return "bolt.fill"
        case .mainSwitch: return "switch.2"
        case .spd:        return "shield.lefthalf.filled"
        case .rcd:        return "shield.checkerboard"
        case .rcbo:       return "shield.fill"
        case .mcb:        return "powerplug.fill"
        case .busbar:     return "rectangle.grid.1x2"
        case .lamp:       return "lightbulb.fill"
        case .socket:     return "poweroutlet.type.f.fill"
        case .motor:      return "fanblades.fill"
        }
    }

    var georgianShort: String {
        switch self {
        case .supply:     return "კვება"
        case .mainSwitch: return "მთავარი"
        case .spd:        return "SPD"
        case .rcd:        return "RCD"
        case .rcbo:       return "RCBO"
        case .mcb:        return "ავტომატი"
        case .busbar:     return "ზოლი"
        case .lamp:       return "ნათურა"
        case .socket:     return "როზეტი"
        case .motor:      return "მოტორი"
        }
    }
}

extension IssueSeverity {
    var color: Color {
        switch self {
        case .error:   return .red
        case .warning: return .orange
        case .info:    return .blue
        }
    }
    var icon: String {
        switch self {
        case .error:   return "xmark.octagon.fill"
        case .warning: return "exclamationmark.triangle.fill"
        case .info:    return "info.circle.fill"
        }
    }
}
